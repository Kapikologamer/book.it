<?php
declare(strict_types=1);

require_once __DIR__ . '/domain.php';
require_once __DIR__ . '/read.php';

function booking_routes(): array
{
    return ['companies','company/show','booking/employees','booking/date','booking/time','booking/slots','booking/confirm','booking/create',
        'client/reservations','client/reservations/show','client/reservations/cancel','client/reservations/reschedule',
        'employee/reservations','employee/reservations/show','employee/reservations/status'];
}

function booking_redirect(string $route, array $params = []): never
{
    header('Location: ./index.php?' . http_build_query(['route' => $route] + $params), true, 303);
    exit;
}

function booking_render(string $route, string $title, ?array $user, array $booking, array $errors = []): never
{
    require __DIR__ . '/../views/layout.php';
    exit;
}

function booking_require_user(?array $user): array
{
    if (!$user) { booking_redirect('login'); }
    return $user;
}

function booking_post(array $allowed): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { deny(405, 'Ta operacja wymaga formularza POST.'); }
    if (!csrf_valid(input($_POST, 'csrf'))) { deny(403, 'Formularz wygasł. Otwórz stronę ponownie.'); }
    if (array_diff(array_keys($_POST), array_merge(['csrf'], $allowed))) { deny(403, 'Formularz zawiera niedozwolone pola.'); }
    foreach ($_POST as $value) { if (!is_string($value) || strlen($value) > 20000) { deny(422, 'Niepoprawne dane formularza.'); } }
}

function booking_handle(string $route, ?array $user): never
{
    try {
        if ($route === 'companies') {
            booking_render($route, 'Odkrywaj firmy', $user, ['catalog' => booking_catalog(db(), $_GET)]);
        }
        if ($route === 'company/show') {
            $company = booking_company(db(), booking_positive_id($_GET, 'id'));
            booking_render($route, $company['name'], $user, ['company' => $company]);
        }
        if ($route === 'booking/employees') {
            $service = booking_employees(db(), booking_positive_id($_GET, 'service_id'));
            booking_render($route, 'Wybierz pracownika', $user, ['service' => $service, 'step' => 2]);
        }
        if ($route === 'booking/date') {
            $serviceId = booking_positive_id($_GET, 'service_id');
            $employeeId = booking_positive_id($_GET, 'employee_id');
            $context = booking_context(db(), $serviceId, $employeeId);
            booking_render($route, 'Wybierz datę', $user, ['context' => $context, 'step' => 3, 'today' => booking_now()->format('Y-m-d'), 'max_date' => booking_now()->modify('+' . BOOKING_HORIZON_DAYS . ' days')->format('Y-m-d')]);
        }
        if ($route === 'booking/time' || $route === 'booking/slots') {
            $serviceId = booking_positive_id($_GET, 'service_id');
            $employeeId = booking_positive_id($_GET, 'employee_id');
            $date = booking_date(input($_GET, 'date'));
            $context = booking_context(db(), $serviceId, $employeeId);
            $slots = booking_available_slots(db(), $serviceId, $employeeId, $date);
            if ($route === 'booking/slots') {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['date' => $date, 'slots' => $slots], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                exit;
            }
            booking_render($route, 'Wybierz godzinę', $user, ['context' => $context, 'date' => $date, 'slots' => $slots, 'step' => 4]);
        }
        if ($route === 'booking/confirm') {
            $user = booking_require_user($user);
            if ($user['role'] === 'admin') { throw new BookingError('Administrator nie tworzy rezerwacji klienta.', 403); }
            $serviceId = booking_positive_id($_GET, 'service_id'); $employeeId = booking_positive_id($_GET, 'employee_id');
            $date = booking_date(input($_GET, 'date')); $start = booking_time(input($_GET, 'start_time'));
            $context = booking_context(db(), $serviceId, $employeeId);
            $slot = null;
            foreach (booking_available_slots(db(), $serviceId, $employeeId, $date) as $candidate) { if ($candidate['start'] . ':00' === $start) { $slot = $candidate; break; } }
            if (!$slot) { throw new BookingError('Ten termin nie jest już dostępny.', 409); }
            $token = bin2hex(random_bytes(32));
            booking_render($route, 'Potwierdź rezerwację', $user, ['context' => $context, 'date' => $date, 'slot' => $slot, 'token' => $token, 'step' => 5]);
        }
        if ($route === 'booking/create') {
            $user = booking_require_user($user); booking_post(['service_id','employee_id','date','start_time','comment','booking_token']);
            if ($user['role'] === 'admin') { throw new BookingError('Administrator nie tworzy rezerwacji klienta.', 403); }
            $id = booking_create(db(), (int) $user['id'], booking_positive_id($_POST, 'service_id'), booking_positive_id($_POST, 'employee_id'), input($_POST, 'date'), input($_POST, 'start_time'), input($_POST, 'comment'), input($_POST, 'booking_token'));
            flash('Rezerwacja została zapisana i oczekuje na potwierdzenie.');
            booking_redirect('client/reservations/show', ['id' => $id]);
        }
        if (str_starts_with($route, 'client/reservations')) {
            $user = booking_require_user($user);
            if ($route === 'client/reservations') {
                booking_render($route, 'Moje rezerwacje', $user, ['rows' => booking_client_list(db(), (int) $user['id'])]);
            }
            $id = booking_positive_id($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET, 'id');
            $reservation = booking_reservation_row(db(), $id, (int) $user['id']);
            if ($route === 'client/reservations/cancel') {
                booking_post(['id']);
                $start = new DateTimeImmutable($reservation['reservation_date'] . ' ' . $reservation['start_time'], new DateTimeZone(BOOKING_TIMEZONE));
                if ($start <= booking_now() || !in_array($reservation['status'], ['pending','confirmed'], true)) { throw new BookingError('Można anulować tylko własną przyszłą aktywną wizytę.', 409); }
                booking_change_status(db(), $id, (int) $user['id'], 'cancelled'); flash('Rezerwacja została anulowana.');
                booking_redirect('client/reservations/show', ['id' => $id]);
            }
            if ($route === 'client/reservations/reschedule') {
                $start = new DateTimeImmutable($reservation['reservation_date'] . ' ' . $reservation['start_time'], new DateTimeZone(BOOKING_TIMEZONE));
                if ($start <= booking_now() || !in_array($reservation['status'], ['pending','confirmed'], true)) { throw new BookingError('Można przełożyć tylko własną przyszłą aktywną wizytę.', 409); }
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    booking_post(['id','date','start_time']);
                    booking_reschedule(db(), $id, (int) $user['id'], input($_POST, 'date'), input($_POST, 'start_time'));
                    flash('Termin wizyty został zmieniony.'); booking_redirect('client/reservations/show', ['id' => $id]);
                }
                $date = input($_GET, 'date') ?: $reservation['reservation_date'];
                $slots = booking_available_slots(db(), (int) $reservation['service_id'], (int) $reservation['employee_id'], booking_date($date), $id);
                booking_render($route, 'Przełóż wizytę', $user, ['reservation' => $reservation, 'date' => $date, 'slots' => $slots]);
            }
            booking_render($route, 'Szczegóły rezerwacji', $user, ['reservation' => $reservation]);
        }
        if (str_starts_with($route, 'employee/reservations')) {
            $user = booking_require_user($user);
            $companyId = booking_positive_id($_GET, 'company_id', false);
            if ($_SERVER['REQUEST_METHOD'] === 'POST') { $companyId = booking_positive_id($_POST, 'company_id'); }
            $ctx = booking_employee_context(db(), (int) $user['id'], $companyId);
            if ($route === 'employee/reservations') {
                booking_render($route, 'Wizyty pracownika', $user, ['employee' => $ctx, 'rows' => booking_employee_list(db(), (int) $ctx['employee_id'])]);
            }
            $source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
            $id = booking_positive_id($source, 'id');
            $reservation = booking_reservation_row(db(), $id, null, (int) $user['id']);
            if ((int) $reservation['company_id'] !== (int) $ctx['company_id']) { throw new BookingError('Nie znaleziono rezerwacji.', 404); }
            if ($route === 'employee/reservations/status') {
                booking_post(['id','company_id','status']);
                booking_change_status(db(), $id, (int) $user['id'], input($_POST, 'status'));
                flash('Status wizyty został zmieniony.'); booking_redirect('employee/reservations/show', ['company_id' => $ctx['company_id'], 'id' => $id]);
            }
            booking_render($route, 'Wizyta pracownika', $user, ['employee' => $ctx, 'reservation' => $reservation]);
        }
        deny(404, 'Taka strona nie istnieje.');
    } catch (BookingError $exception) {
        if (in_array($exception->status, [403,404,405], true)) { deny($exception->status, $exception->getMessage()); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            flash($exception->getMessage());
            if (str_starts_with($route, 'employee/')) {
                booking_redirect('employee/reservations', array_filter(['company_id' => input($_POST, 'company_id')]));
            }
            if ($route === 'booking/create') {
                booking_redirect('booking/time', ['service_id' => input($_POST, 'service_id'), 'employee_id' => input($_POST, 'employee_id'), 'date' => input($_POST, 'date')]);
            }
            booking_redirect('client/reservations');
        }
        http_response_code($exception->status);
        booking_render($route, 'Nie można wykonać operacji', $user, ['error' => $exception->getMessage()], [$exception->field => $exception->getMessage()]);
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062 && $route === 'booking/create') {
            flash('Ta rezerwacja została już zapisana.'); booking_redirect('client/reservations');
        }
        throw $exception;
    }
}
