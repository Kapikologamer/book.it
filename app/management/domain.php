<?php
declare(strict_types=1);

require_once __DIR__ . '/media.php';

final class ManagementError extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422, public readonly string $field = 'form')
    {
        parent::__construct($message);
    }
}

function management_sql(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function management_transaction(PDO $pdo): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Management requires a caller-owned transaction.');
    }
}

function management_text(array $data, string $field, int $max, bool $required = false): string
{
    $value = $data[$field] ?? '';
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        throw new ManagementError('Nieprawidłowy tekst.', 422, $field);
    }
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
        throw new ManagementError('Pole jest wymagane lub przekracza dopuszczalną długość: ' . $max . '.', 422, $field);
    }
    return $value;
}

function management_integer(mixed $value, string $field, int $min = 1, int $max = PHP_INT_MAX): int
{
    if ((!is_string($value) && !is_int($value)) || !preg_match('/\A[0-9]+\z/', (string) $value)
        || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < $min || (int) $value > $max) {
        throw new ManagementError('Podaj poprawną liczbę całkowitą.', 422, $field);
    }
    return (int) $value;
}

function management_confirm(array $data): void
{
    if (!in_array($data['confirmed'] ?? null, [1, '1'], true)) {
        throw new ManagementError('Potwierdź wykonanie operacji.', 422, 'confirmed');
    }
}

function management_company(PDO $pdo, array $ctx, int $id, bool $allowInactive = false): array
{
    if ($id < 1 || (($ctx['company_id'] ?? null) !== null && (int) $ctx['company_id'] !== $id)) {
        throw new ManagementError('Nie znaleziono obiektu.', 404);
    }
    $company = management_sql($pdo, 'SELECT * FROM companies WHERE id = ? FOR UPDATE', [$id])->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        throw new ManagementError('Nie znaleziono obiektu.', 404);
    }
    if (!$ctx['admin']) {
        $member = management_sql($pdo, 'SELECT role, active FROM company_memberships WHERE company_id = ? AND user_id = ? FOR UPDATE',
            [$id, $ctx['user']['id']])->fetch(PDO::FETCH_ASSOC);
        if (!$member || !$member['active'] || $member['role'] !== 'owner') {
            throw new ManagementError('Brak uprawnień do firmy.', 403);
        }
    }
    if (!$company['active'] && !$allowInactive) {
        throw new ManagementError('Firma jest nieaktywna.', 409);
    }
    return $company;
}

function management_row(PDO $pdo, string $table, int $id): array
{
    if (!in_array($table, ['services', 'employees', 'service_categories', 'employee_availability', 'employee_time_off', 'reservations', 'employee_invitations', 'users'], true)) {
        throw new LogicException('Unsupported management table.');
    }
    $row = management_sql($pdo, "SELECT * FROM $table WHERE id = ? FOR UPDATE", [$id])->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new ManagementError('Nie znaleziono obiektu.', 404);
    }
    return $row;
}

function management_audit(PDO $pdo, array $ctx, string $entity, int $id, string $action, string $summary, ?int $companyId = null): void
{
    management_transaction($pdo);
    foreach (['entity' => [$entity, 40], 'action' => [$action, 40], 'summary' => [$summary, 1000]] as $key => [$value, $limit]) {
        management_text([$key => $value], $key, $limit, true);
    }
    management_sql($pdo, 'INSERT INTO admin_audit_logs (actor_id, company_id, actor_role, entity_type, entity_id, action, summary) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$ctx['user']['id'], $companyId, $ctx['audit_role'] ?? ($ctx['admin'] ? 'admin' : 'owner'), $entity, $id, $action, $summary]);
}

function management_mutate(PDO $pdo, array $ctx, string $module, string $action, int $id, array $data, array $files = []): void
{
    management_transaction($pdo);
    $allowed = [
        'categories' => ['save', 'delete', 'toggle'], 'services' => ['save', 'delete', 'toggle'],
        'employees' => ['save', 'toggle', 'invite'], 'availability' => ['save', 'delete'],
        'time-off' => ['save', 'delete'], 'reservations' => ['status'],
        'users' => ['toggle', 'role'], 'companies' => ['toggle'], 'profile' => ['save'],
        'invitations' => ['invite', 'cancel', 'accept', 'decline'],
    ];
    if ($id < 0 || !in_array($action, $allowed[$module] ?? [], true)) {
        throw new ManagementError('Niedozwolona operacja.', 405);
    }
    // All administrator locks precede actor locks, including when two admins disable each other.
    $admins = management_sql($pdo, "SELECT id, active FROM users WHERE role = 'admin' ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
    $actor = management_row($pdo, 'users', (int) ($ctx['user']['id'] ?? 0));
    if (!$actor['active'] || !isset($ctx['user']['auth_version']) || (int) $actor['auth_version'] !== (int) $ctx['user']['auth_version']) {
        throw new ManagementError('Sesja wygasła. Zaloguj się ponownie.', 403);
    }
    $ctx['admin'] = ($ctx['admin'] ?? false) === true;
    if ($ctx['admin'] && $actor['role'] !== 'admin') {
        throw new ManagementError('Brak uprawnień administratora.', 403);
    }
    $ctx['user'] = $actor;
    if (in_array($module, ['categories', 'users', 'companies'], true) && !$ctx['admin']) {
        throw new ManagementError('Brak uprawnień administratora.', 403);
    }
    $responding = $module === 'invitations' && in_array($action, ['accept', 'decline'], true);
    if (!$responding && !$ctx['admin']) {
        management_company($pdo, $ctx, (int) ($ctx['company_id'] ?? 0));
    }
    if (in_array($action, ['delete', 'toggle', 'cancel', 'status', 'role'], true) || $module === 'reservations') {
        management_confirm($data);
    }
    try {
        switch ($module) {
            case 'categories':
                management_category($pdo, $ctx, $action, $id, $data);
                break;
            case 'services':
                management_service($pdo, $ctx, $action, $id, $data);
                break;
            case 'employees':
                if ($action === 'invite') {
                    management_invitation($pdo, $ctx, 'invite', $id, $data);
                } else {
                    management_employee($pdo, $ctx, $action, $id, $data);
                }
                break;
            case 'availability':
            case 'time-off':
                management_schedule($pdo, $ctx, $module, $action, $id, $data);
                break;
            case 'reservations':
                management_reservation($pdo, $ctx, $action, $id, $data);
                break;
            case 'users':
                $row = management_row($pdo, 'users', $id);
                $activeAdmins = count(array_filter($admins, static fn(array $a): bool => (bool) $a['active']));
                $newRole = $action === 'role' ? management_text($data, 'role', 20, true) : $row['role'];
                if (!in_array($newRole, ['client', 'employee', 'admin'], true)) {
                    throw new ManagementError('Niepoprawna rola globalna.', 422, 'role');
                }
                if ($row['active'] && $row['role'] === 'admin' && ($action === 'toggle' || $newRole !== 'admin') && $activeAdmins <= 1) {
                    throw new ManagementError('Nie można wyłączyć ani zmienić roli ostatniego aktywnego administratora.', 409);
                }
                if ($action === 'role') {
                    management_sql($pdo, 'UPDATE users SET role = ?, auth_version = auth_version + 1 WHERE id = ?', [$newRole, $id]);
                    management_audit($pdo, $ctx, 'users', $id, 'role', 'Zmiana roli globalnej: ' . $row['role'] . ' -> ' . $newRole . '.');
                } else {
                    management_sql($pdo, 'UPDATE users SET active = ?, auth_version = auth_version + 1 WHERE id = ?', [(int) !$row['active'], $id]);
                    management_audit($pdo, $ctx, 'users', $id, 'toggle', $row['active'] ? 'Dezaktywacja konta.' : 'Aktywacja konta.');
                }
                break;
            case 'companies':
                $row = management_company($pdo, $ctx, $id, true);
                management_sql($pdo, 'UPDATE companies SET active = ? WHERE id = ?', [(int) !$row['active'], $id]);
                management_audit($pdo, $ctx, 'companies', $id, 'toggle', $row['active'] ? 'Dezaktywacja firmy.' : 'Aktywacja firmy.', $id);
                break;
            case 'profile':
                management_profile($pdo, $ctx, $id, $data, $files);
                break;
            case 'invitations':
                management_invitation($pdo, $ctx, $action, $id, $data);
                break;
        }
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            throw new ManagementError('Rekord już istnieje lub ma powiązania uniemożliwiające tę operację.', 409);
        }
        throw $exception;
    }
}

function management_category(PDO $pdo, array $ctx, string $action, int $id, array $data): void
{
    $row = $id ? management_row($pdo, 'service_categories', $id) : null;
    if ($action === 'save') {
        $values = [management_text($data, 'name', 100, true), management_text($data, 'description', 500)];
        if ($row) {
            management_sql($pdo, 'UPDATE service_categories SET name = ?, description = ? WHERE id = ?', [...$values, $id]);
        } else {
            management_sql($pdo, 'INSERT INTO service_categories (name, description) VALUES (?, ?)', $values);
            $id = (int) $pdo->lastInsertId();
        }
    } elseif (!$row) {
        throw new ManagementError('Nie znaleziono kategorii.', 404);
    } elseif ($action === 'toggle') {
        management_sql($pdo, 'UPDATE service_categories SET active = ? WHERE id = ?', [(int) !$row['active'], $id]);
    } else {
        if (management_sql($pdo, 'SELECT id FROM services WHERE category_id = ? LIMIT 1 FOR UPDATE', [$id])->fetchColumn()) {
            throw new ManagementError('Kategoria ma usługi. Zamiast usuwania użyj dezaktywacji.', 409);
        }
        management_sql($pdo, 'DELETE FROM service_categories WHERE id = ?', [$id]);
    }
    management_audit($pdo, $ctx, 'categories', $id, $action, 'Zmiana kategorii: ' . $action . '.');
}

function management_service(PDO $pdo, array $ctx, string $action, int $id, array $data): void
{
    $row = $id ? management_row($pdo, 'services', $id) : null;
    $companyId = (int) ($row['company_id'] ?? $ctx['company_id'] ?? 0);
    management_company($pdo, $ctx, $companyId);
    if ($action === 'save') {
        $categoryId = management_integer($data['category_id'] ?? null, 'category_id');
        $category = management_row($pdo, 'service_categories', $categoryId);
        if (!$category['active']) {
            throw new ManagementError('Wybierz aktywną kategorię.', 422, 'category_id');
        }
        $price = management_text($data, 'price', 11, true);
        if (!preg_match('/\A(?:0|[1-9][0-9]{0,7})(?:[.,][0-9]{1,2})?\z/', $price)) {
            throw new ManagementError('Cena musi mieścić się w zakresie 0–99999999,99.', 422, 'price');
        }
        $duration = management_integer(array_key_exists('duration_minutes', $data) ? $data['duration_minutes'] : ($row['duration_minutes'] ?? 45), 'duration_minutes', 1, 1440);
        $values = [management_text($data, 'name', 120, true), management_text($data, 'description', 1000), $categoryId, str_replace(',', '.', $price), $duration];
        if ($row) {
            management_sql($pdo, 'UPDATE services SET name = ?, description = ?, category_id = ?, price = ?, duration_minutes = ? WHERE id = ?', [...$values, $id]);
        } else {
            management_sql($pdo, 'INSERT INTO services (name, description, category_id, price, duration_minutes, company_id) VALUES (?, ?, ?, ?, ?, ?)', [...$values, $companyId]);
            $id = (int) $pdo->lastInsertId();
        }
    } elseif (!$row) {
        throw new ManagementError('Nie znaleziono usługi.', 404);
    } elseif ($action === 'toggle') {
        if (!$row['active'] && !management_row($pdo, 'service_categories', (int) $row['category_id'])['active']) {
            throw new ManagementError('Kategoria usługi jest nieaktywna.', 409);
        }
        management_sql($pdo, 'UPDATE services SET active = ? WHERE id = ?', [(int) !$row['active'], $id]);
    } else {
        if (management_sql($pdo, 'SELECT id FROM reservations WHERE service_id = ? LIMIT 1 FOR UPDATE', [$id])->fetchColumn()) {
            throw new ManagementError('Usługa ma historię rezerwacji. Użyj dezaktywacji.', 409);
        }
        management_sql($pdo, 'DELETE FROM employee_services WHERE service_id = ? AND company_id = ?', [$id, $companyId]);
        management_sql($pdo, 'DELETE FROM services WHERE id = ?', [$id]);
    }
    management_audit($pdo, $ctx, 'services', $id, $action, 'Zmiana usługi: ' . $action . '.', $companyId);
}

function management_employee(PDO $pdo, array $ctx, string $action, int $id, array $data): void
{
    if (!$id) {
        throw new ManagementError('Dodanie pracownika wymaga przyjęcia zaproszenia.', 422);
    }
    $row = management_row($pdo, 'employees', $id);
    $companyId = (int) $row['company_id'];
    management_company($pdo, $ctx, $companyId);
    if ($action === 'toggle') {
        if (!$row['active']) {
            $user = management_row($pdo, 'users', (int) $row['user_id']);
            $membership = management_sql($pdo, 'SELECT active FROM company_memberships WHERE user_id = ? AND company_id = ? FOR UPDATE', [$row['user_id'], $companyId])->fetchColumn();
            if (!$user['active'] || !$membership) {
                throw new ManagementError('Konto lub członkostwo pracownika jest nieaktywne.', 409);
            }
        }
        management_sql($pdo, 'UPDATE employees SET active = ? WHERE id = ?', [(int) !$row['active'], $id]);
    } else {
        $description = management_text($data, 'description', 1000);
        $ids = $data['service_ids'] ?? [];
        if (!is_array($ids) || count($ids) > 1000) {
            throw new ManagementError('Nieprawidłowa lista usług.', 422, 'service_ids');
        }
        $ids = array_unique(array_map(static fn($value): int => management_integer($value, 'service_ids'), $ids));
        sort($ids, SORT_NUMERIC);
        foreach ($ids as $serviceId) {
            $service = management_row($pdo, 'services', $serviceId);
            if ((int) $service['company_id'] !== $companyId) {
                throw new ManagementError('Nie znaleziono usługi.', 404, 'service_ids');
            }
            if (!$service['active'] || !management_row($pdo, 'service_categories', (int) $service['category_id'])['active']) {
                throw new ManagementError('Można przypisywać tylko aktywne usługi z aktywnej kategorii.', 422, 'service_ids');
            }
        }
        management_sql($pdo, 'UPDATE employees SET description = ? WHERE id = ?', [$description, $id]);
        management_sql($pdo, 'UPDATE employee_services SET active = 0 WHERE employee_id = ? AND company_id = ?', [$id, $companyId]);
        foreach ($ids as $serviceId) {
            management_sql($pdo, 'INSERT INTO employee_services (employee_id, service_id, company_id, active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE active = 1', [$id, $serviceId, $companyId]);
        }
    }
    management_audit($pdo, $ctx, 'employees', $id, $action, 'Zmiana profilu pracownika: ' . $action . '.', $companyId);
}

function management_time(array $data, string $field): string
{
    $value = management_text($data, $field, 8, true);
    if (!preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/', $value)) {
        throw new ManagementError('Podaj poprawną godzinę.', 422, $field);
    }
    return strlen($value) === 5 ? $value . ':00' : $value;
}

function management_datetime(array $data, string $field): string
{
    $value = str_replace('T', ' ', management_text($data, $field, 19, true));
    if (strlen($value) === 16) {
        $value .= ':00';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    if (!$date || $date->format('Y-m-d H:i:s') !== $value || $date->format('Y') < '1000') {
        throw new ManagementError('Podaj poprawną datę i godzinę.', 422, $field);
    }
    return $value;
}

function management_schedule(PDO $pdo, array $ctx, string $module, string $action, int $id, array $data): void
{
    $table = $module === 'availability' ? 'employee_availability' : 'employee_time_off';
    $row = $id ? management_row($pdo, $table, $id) : null;
    $employeeId = $row ? (int) $row['employee_id'] : management_integer($data['employee_id'] ?? null, 'employee_id');
    if ($row && isset($data['employee_id']) && management_integer($data['employee_id'], 'employee_id') !== $employeeId) {
        throw new ManagementError('Nie można przenosić grafiku między pracownikami.', 422, 'employee_id');
    }
    // Future booking writers must lock this employee before checking and inserting a reservation.
    $employee = management_row($pdo, 'employees', $employeeId);
    $companyId = (int) $employee['company_id'];
    management_company($pdo, $ctx, $companyId);
    if ($action === 'delete') {
        if (!$row) {
            throw new ManagementError('Nie znaleziono przedziału.', 404);
        }
        if ($module === 'availability') {
            management_check_coverage($pdo, $employeeId, $id, null);
        }
        management_sql($pdo, "DELETE FROM $table WHERE id = ?", [$id]);
    } elseif ($module === 'availability') {
        $day = management_integer($data['day_of_week'] ?? null, 'day_of_week', 1, 7);
        $start = management_time($data, 'start_time');
        $end = management_time($data, 'end_time');
        if ($end <= $start) {
            throw new ManagementError('Koniec musi następować po początku.', 422, 'end_time');
        }
        if (management_sql($pdo, 'SELECT id FROM employee_availability WHERE employee_id = ? AND day_of_week = ? AND id <> ? AND start_time < ? AND end_time > ? LIMIT 1 FOR UPDATE', [$employeeId, $day, $id, $end, $start])->fetchColumn()) {
            throw new ManagementError('Przedział nakłada się na istniejące godziny pracy.', 409);
        }
        management_check_coverage($pdo, $employeeId, $id, ['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end]);
        if ($row) {
            management_sql($pdo, 'UPDATE employee_availability SET day_of_week = ?, start_time = ?, end_time = ? WHERE id = ?', [$day, $start, $end, $id]);
        } else {
            management_sql($pdo, 'INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)', [$employeeId, $day, $start, $end]);
            $id = (int) $pdo->lastInsertId();
        }
    } else {
        $start = management_datetime($data, 'starts_at');
        $end = management_datetime($data, 'ends_at');
        $kind = management_text($data, 'kind', 10, true);
        $reason = management_text($data, 'reason', 255);
        if ($end <= $start) {
            throw new ManagementError('Koniec musi następować po początku.', 422, 'ends_at');
        }
        if (!in_array($kind, ['holiday', 'break', 'day_off'], true)) {
            throw new ManagementError('Nieprawidłowy rodzaj nieobecności.', 422, 'kind');
        }
        if (management_sql($pdo, 'SELECT id FROM employee_time_off WHERE employee_id = ? AND id <> ? AND starts_at < ? AND ends_at > ? LIMIT 1 FOR UPDATE', [$employeeId, $id, $end, $start])->fetchColumn()) {
            throw new ManagementError('Nieobecności nie mogą się nakładać.', 409);
        }
        if (management_sql($pdo, "SELECT id FROM reservations WHERE employee_id = ? AND status IN ('pending','confirmed') AND TIMESTAMP(reservation_date, start_time) < ? AND TIMESTAMP(reservation_date, end_time) > ? LIMIT 1 FOR UPDATE", [$employeeId, $end, $start])->fetchColumn()) {
            throw new ManagementError('Nieobecność koliduje z aktywną rezerwacją. Najpierw obsłuż rezerwację.', 409);
        }
        if ($row) {
            management_sql($pdo, 'UPDATE employee_time_off SET starts_at = ?, ends_at = ?, kind = ?, reason = ? WHERE id = ?', [$start, $end, $kind, $reason, $id]);
        } else {
            management_sql($pdo, 'INSERT INTO employee_time_off (employee_id, starts_at, ends_at, kind, reason) VALUES (?, ?, ?, ?, ?)', [$employeeId, $start, $end, $kind, $reason]);
            $id = (int) $pdo->lastInsertId();
        }
    }
    management_audit($pdo, $ctx, $module, $id, $action, 'Zmiana dostępności pracownika #' . $employeeId . ': ' . $action . '.', $companyId);
}

function management_check_coverage(PDO $pdo, int $employeeId, int $excludedId, ?array $replacement): void
{
    $slots = management_sql($pdo, 'SELECT day_of_week, start_time, end_time FROM employee_availability WHERE employee_id = ? AND id <> ? ORDER BY day_of_week, start_time FOR UPDATE', [$employeeId, $excludedId])->fetchAll(PDO::FETCH_ASSOC);
    if ($replacement) {
        $slots[] = $replacement;
    }
    usort($slots, static fn(array $a, array $b): int => [$a['day_of_week'], $a['start_time']] <=> [$b['day_of_week'], $b['start_time']]);
    $reservations = management_sql($pdo, "SELECT WEEKDAY(reservation_date) + 1 AS day_of_week, start_time, end_time FROM reservations WHERE employee_id = ? AND status IN ('pending','confirmed') FOR UPDATE", [$employeeId])->fetchAll(PDO::FETCH_ASSOC);
    foreach ($reservations as $reservation) {
        $coveredUntil = $reservation['start_time'];
        foreach ($slots as $slot) {
            if ((int) $slot['day_of_week'] === (int) $reservation['day_of_week'] && $slot['start_time'] <= $coveredUntil && $slot['end_time'] > $coveredUntil) {
                $coveredUntil = $slot['end_time'];
            }
        }
        if ($coveredUntil < $reservation['end_time']) {
            throw new ManagementError('Zmiana grafiku pozostawi aktywną rezerwację poza godzinami pracy.', 409);
        }
    }
}

function management_reservation(PDO $pdo, array $ctx, string $action, int $id, array $data): void
{
    $row = management_row($pdo, 'reservations', $id);
    $employee = management_row($pdo, 'employees', (int) $row['employee_id']);
    $service = management_row($pdo, 'services', (int) $row['service_id']);
    $companyId = (int) $employee['company_id'];
    management_company($pdo, $ctx, $companyId);
    if ((int) $service['company_id'] !== $companyId) {
        throw new ManagementError('Niespójne powiązanie rezerwacji.', 409);
    }
    $status = management_text($data, 'status', 20, true);
    $transitions = ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['completed', 'cancelled']];
    if (!in_array($status, $transitions[$row['status']] ?? [], true)) {
        throw new ManagementError('Niedozwolona zmiana statusu rezerwacji.', 409, 'status');
    }
    if ($status === 'confirmed') {
        management_check_coverage($pdo, (int) $employee['id'], 0, null);
        $start = $row['reservation_date'] . ' ' . $row['start_time'];
        $end = $row['reservation_date'] . ' ' . $row['end_time'];
        if (management_sql($pdo, 'SELECT id FROM employee_time_off WHERE employee_id = ? AND starts_at < ? AND ends_at > ? LIMIT 1 FOR UPDATE', [$employee['id'], $end, $start])->fetchColumn()
            || management_sql($pdo, "SELECT id FROM reservations WHERE employee_id = ? AND id <> ? AND reservation_date = ? AND status IN ('pending','confirmed') AND start_time < ? AND end_time > ? LIMIT 1 FOR UPDATE", [$employee['id'], $id, $row['reservation_date'], $row['end_time'], $row['start_time']])->fetchColumn()) {
            throw new ManagementError('Rezerwacja koliduje z inną wizytą lub nieobecnością.', 409);
        }
    }
    management_sql($pdo, 'UPDATE reservations SET status = ? WHERE id = ?', [$status, $id]);
    management_audit($pdo, $ctx, 'reservations', $id, 'status', $row['status'] . ' -> ' . $status, $companyId);
}

function management_invitation(PDO $pdo, array $ctx, string $action, int $id, array $data): void
{
    if ($action === 'invite') {
        $companyId = (int) ($ctx['company_id'] ?? 0);
        management_company($pdo, $ctx, $companyId);
        $email = strtolower(management_text($data, 'email', 190, true));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ManagementError('Podaj poprawny adres e-mail.', 422, 'email');
        }
        if (management_sql($pdo, "SELECT id FROM employee_invitations WHERE company_id = ? AND email = ? AND status = 'pending' AND expires_at > CURRENT_TIMESTAMP LIMIT 1 FOR UPDATE", [$companyId, $email])->fetchColumn()) {
            throw new ManagementError('Aktywne zaproszenie już istnieje.', 409, 'email');
        }
        management_sql($pdo, 'INSERT INTO employee_invitations (company_id, email, invited_by, expires_at) VALUES (?, ?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 7 DAY))', [$companyId, $email, $ctx['user']['id']]);
        $id = (int) $pdo->lastInsertId();
    } else {
        $invitation = management_row($pdo, 'employee_invitations', $id);
        $companyId = (int) $invitation['company_id'];
        if ($action === 'cancel') {
            management_company($pdo, $ctx, $companyId);
        } else {
            if (strtolower(trim($ctx['user']['email'])) !== strtolower(trim($invitation['email']))) {
                throw new ManagementError('Nie znaleziono zaproszenia.', 404);
            }
            $company = management_sql($pdo, 'SELECT active FROM companies WHERE id = ? FOR UPDATE', [$companyId])->fetch(PDO::FETCH_ASSOC);
            if (!$company || !$company['active']) {
                throw new ManagementError('Firma jest nieaktywna.', 409);
            }
            $ctx['audit_role'] = $ctx['user']['role'];
        }
        $valid = management_sql($pdo, "SELECT id FROM employee_invitations WHERE id = ? AND status = 'pending' AND expires_at > CURRENT_TIMESTAMP FOR UPDATE", [$id])->fetchColumn();
        if (!$valid) {
            throw new ManagementError('Zaproszenie wygasło lub zostało już obsłużone.', 409);
        }
        if ($action === 'accept') {
            $userId = (int) $ctx['user']['id'];
            $employee = management_sql($pdo, 'SELECT id, company_id FROM employees WHERE user_id = ? FOR UPDATE', [$userId])->fetch(PDO::FETCH_ASSOC);
            if ($employee && (int) $employee['company_id'] !== $companyId) {
                throw new ManagementError('Konto ma już profil pracownika w innej firmie.', 409);
            }
            $membership = management_sql($pdo, 'SELECT role, active FROM company_memberships WHERE user_id = ? AND company_id = ? FOR UPDATE', [$userId, $companyId])->fetch(PDO::FETCH_ASSOC);
            if ($membership && (!$membership['active'] || $membership['role'] !== 'employee')) {
                throw new ManagementError('Istniejące członkostwo uniemożliwia przyjęcie zaproszenia.', 409);
            }
            if (!$membership) {
                management_sql($pdo, "INSERT INTO company_memberships (user_id, company_id, role) VALUES (?, ?, 'employee')", [$userId, $companyId]);
            }
            if (!$employee) {
                management_sql($pdo, 'INSERT INTO employees (user_id, company_id) VALUES (?, ?)', [$userId, $companyId]);
            } else {
                management_sql($pdo, 'UPDATE employees SET active = 1 WHERE id = ?', [$employee['id']]);
            }
        }
        $status = ['accept' => 'accepted', 'decline' => 'declined', 'cancel' => 'cancelled'][$action];
        management_sql($pdo, 'UPDATE employee_invitations SET status = ? WHERE id = ?', [$status, $id]);
    }
    management_audit($pdo, $ctx, 'invitations', $id, $action, 'Zaproszenie pracownika: ' . $action . '.', $companyId);
}

function management_profile(PDO $pdo, array $ctx, int $id, array $data, array $files): void
{
    $companyId = $id ?: (int) ($ctx['company_id'] ?? 0);
    $company = management_company($pdo, $ctx, $companyId);
    $values = [];
    foreach (['name' => 120, 'short_description' => 255, 'description' => 15000, 'phone' => 30, 'email' => 190, 'city' => 100, 'address' => 255, 'website' => 255] as $field => $max) {
        $values[$field] = management_text($data, $field, $max, $field === 'name');
    }
    if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        throw new ManagementError('Podaj poprawny adres e-mail.', 422, 'email');
    }
    if ($values['phone'] !== '' && (!preg_match('/\A\+?[0-9 ()-]{7,30}\z/', $values['phone']) || strlen(preg_replace('/[^0-9]/', '', $values['phone'])) < 7)) {
        throw new ManagementError('Podaj poprawny numer telefonu.', 422, 'phone');
    }
    if ($values['website'] !== '' && (!filter_var($values['website'], FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($values['website'], PHP_URL_SCHEME)), ['https', 'http'], true) || parse_url($values['website'], PHP_URL_USER) !== null || parse_url($values['website'], PHP_URL_PASS) !== null)) {
        throw new ManagementError('Podaj adres HTTP lub HTTPS bez danych logowania.', 422, 'website');
    }
    foreach (['logo', 'banner'] as $kind) {
        $column = $kind . '_path';
        $values[$column] = $company[$column];
        $remove = in_array($data['remove_' . $kind] ?? null, ['1', 1], true);
        $upload = $files[$kind] ?? null;
        if ($upload !== null && !is_array($upload)) {
            throw new ManagementError('Nieprawidłowy upload.', 422, $kind);
        }
        $hasUpload = $upload !== null && ($upload['error'] ?? null) !== UPLOAD_ERR_NO_FILE;
        if ($hasUpload && $remove) {
            throw new ManagementError('Wybierz usunięcie albo nowy obraz.', 422, $kind);
        }
        if ($hasUpload) {
            $values[$column] = management_media_upload($upload, $kind);
        } elseif ($remove) {
            $values[$column] = null;
        }
        if ($values[$column] !== $company[$column] && $company[$column] !== null) {
            management_media_retire($company[$column]);
        }
    }
    management_sql($pdo, 'UPDATE companies SET name = ?, short_description = ?, description = ?, phone = ?, email = ?, city = ?, address = ?, website = ?, logo_path = ?, banner_path = ? WHERE id = ?', [...array_values($values), $companyId]);
    management_audit($pdo, $ctx, 'profile', $companyId, 'save', 'Aktualizacja danych marki i mediów.', $companyId);
}

function management_refresh_readiness(PDO $pdo, ?int $companyId = null): void
{
    management_transaction($pdo);
    $companies = management_sql($pdo, 'SELECT id FROM companies' . ($companyId === null ? '' : ' WHERE id = ?') . ' ORDER BY id FOR UPDATE', $companyId === null ? [] : [$companyId])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($companies as $id) {
        management_sql($pdo, "UPDATE companies c SET onboarding_status = CASE WHEN c.active = 1
            AND TRIM(c.name) <> '' AND TRIM(c.short_description) <> '' AND TRIM(c.phone) <> ''
            AND TRIM(c.email) <> '' AND TRIM(c.city) <> '' AND TRIM(c.address) <> ''
            AND EXISTS (SELECT 1 FROM employees e JOIN users u ON u.id = e.user_id AND u.active = 1
                JOIN company_memberships m ON m.user_id = e.user_id AND m.company_id = e.company_id AND m.active = 1
                JOIN employee_services es ON es.employee_id = e.id AND es.company_id = e.company_id AND es.active = 1
                JOIN services s ON s.id = es.service_id AND s.company_id = e.company_id AND s.active = 1
                JOIN service_categories sc ON sc.id = s.category_id AND sc.active = 1
                WHERE e.company_id = c.id AND e.active = 1
                AND EXISTS (SELECT 1 FROM employee_availability a WHERE a.employee_id = e.id AND a.day_of_week BETWEEN 1 AND 7 AND a.start_time < a.end_time))
            THEN 'ready' ELSE 'pending' END WHERE c.id = ?", [(int) $id]);
    }
}
