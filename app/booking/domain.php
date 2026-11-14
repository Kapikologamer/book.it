<?php
declare(strict_types=1);

final class BookingError extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422, public readonly string $field = 'form')
    {
        parent::__construct($message);
    }
}

const BOOKING_TIMEZONE = 'Europe/Warsaw';
const BOOKING_HORIZON_DAYS = 90;
const BOOKING_SLOT_MINUTES = 15;

function booking_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone(BOOKING_TIMEZONE));
}

function booking_date(string $value): string
{
    if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value)) {
        throw new BookingError('Wybierz poprawną datę.', 422, 'date');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone(BOOKING_TIMEZONE));
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new BookingError('Wybierz poprawną datę.', 422, 'date');
    }
    $today = booking_now()->setTime(0, 0);
    if ($date < $today || $date > $today->modify('+' . BOOKING_HORIZON_DAYS . ' days')) {
        throw new BookingError('Datę można wybrać od dziś do ' . BOOKING_HORIZON_DAYS . ' dni naprzód.', 422, 'date');
    }
    return $value;
}

function booking_time(string $value): string
{
    if (!preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?\z/', $value)) {
        throw new BookingError('Wybierz poprawną godzinę.', 422, 'start_time');
    }
    return substr($value, 0, 5) . ':00';
}

function booking_overlaps(string $start, string $end, string $otherStart, string $otherEnd): bool
{
    return $start < $otherEnd && $end > $otherStart;
}

function booking_merge_intervals(array $intervals): array
{
    usort($intervals, static fn(array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
    $merged = [];
    foreach ($intervals as $interval) {
        if (!$merged || $interval['start'] > $merged[array_key_last($merged)]['end']) {
            $merged[] = $interval;
            continue;
        }
        $last = array_key_last($merged);
        if ($interval['end'] > $merged[$last]['end']) {
            $merged[$last]['end'] = $interval['end'];
        }
    }
    return $merged;
}

function booking_context(PDO $pdo, int $serviceId, int $employeeId): array
{
    $sql = "SELECT s.id service_id,s.company_id,s.name service_name,s.description service_description,
                   s.duration_minutes,s.price,c.id company_id,c.name company_name,c.city,c.active company_active,
                   c.onboarding_status,p.id employee_id,p.user_id employee_user_id,
                   CONCAT(u.name,' ',u.surname) employee_name
            FROM services s
            JOIN service_categories sc ON sc.id=s.category_id AND sc.active=1
            JOIN companies c ON c.id=s.company_id
            JOIN employee_services es ON es.service_id=s.id AND es.company_id=s.company_id AND es.active=1
            JOIN employees p ON p.id=es.employee_id AND p.company_id=es.company_id AND p.active=1
            JOIN users u ON u.id=p.user_id AND u.active=1
            JOIN company_memberships m ON m.user_id=p.user_id AND m.company_id=p.company_id AND m.role='employee' AND m.active=1
            WHERE s.id=? AND p.id=? AND s.active=1 AND c.active=1 AND c.onboarding_status='ready'";
    $row = query($pdo, $sql, [$serviceId, $employeeId])->fetch();
    if (!$row) {
        throw new BookingError('Ta usługa lub pracownik nie są dostępni.', 404);
    }
    return $row;
}

function booking_dst_transition_day(string $date): bool
{
    $tz = new DateTimeZone(BOOKING_TIMEZONE);
    $start = new DateTimeImmutable($date . ' 00:00:00', $tz);
    return $start->getOffset() !== $start->modify('+1 day')->getOffset();
}

function booking_available_slots(PDO $pdo, int $serviceId, int $employeeId, string $date, ?int $excludeReservationId = null): array
{
    $date = booking_date($date);
    $context = booking_context($pdo, $serviceId, $employeeId);
    // The SQL model stores local wall-clock TIME values. Ambiguous DST transition days are rejected conservatively.
    if (booking_dst_transition_day($date)) {
        return [];
    }
    $day = (int) (new DateTimeImmutable($date, new DateTimeZone(BOOKING_TIMEZONE)))->format('N');
    $rows = query($pdo, 'SELECT start_time, end_time FROM employee_availability WHERE employee_id=? AND day_of_week=? ORDER BY start_time,end_time', [$employeeId, $day])->fetchAll();
    $availability = booking_merge_intervals(array_map(static fn(array $r): array => ['start' => $r['start_time'], 'end' => $r['end_time']], $rows));
    if (!$availability) {
        return [];
    }
    $dayStart = $date . ' 00:00:00';
    $dayEnd = (new DateTimeImmutable($date, new DateTimeZone(BOOKING_TIMEZONE)))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    $timeOff = query($pdo, 'SELECT starts_at,ends_at FROM employee_time_off WHERE employee_id=? AND starts_at < ? AND ends_at > ?', [$employeeId, $dayEnd, $dayStart])->fetchAll();
    $params = [$employeeId, $date];
    $exclude = '';
    if ($excludeReservationId !== null) {
        $exclude = ' AND id<>?';
        $params[] = $excludeReservationId;
    }
    $reservations = query($pdo, "SELECT start_time,end_time FROM reservations WHERE employee_id=? AND reservation_date=? AND status IN ('pending','confirmed')$exclude", $params)->fetchAll();
    $duration = (int) $context['duration_minutes'];
    $now = booking_now();
    $slots = [];
    foreach ($availability as $window) {
        $cursor = new DateTimeImmutable($date . ' ' . $window['start'], new DateTimeZone(BOOKING_TIMEZONE));
        $windowEnd = new DateTimeImmutable($date . ' ' . $window['end'], new DateTimeZone(BOOKING_TIMEZONE));
        while (($end = $cursor->modify('+' . $duration . ' minutes')) <= $windowEnd) {
            $startTime = $cursor->format('H:i:s');
            $endTime = $end->format('H:i:s');
            $startStamp = $date . ' ' . $startTime;
            $endStamp = $date . ' ' . $endTime;
            $blocked = $cursor <= $now;
            foreach ($timeOff as $off) {
                $blocked = $blocked || booking_overlaps($startStamp, $endStamp, $off['starts_at'], $off['ends_at']);
            }
            foreach ($reservations as $reservation) {
                $blocked = $blocked || booking_overlaps($startTime, $endTime, $reservation['start_time'], $reservation['end_time']);
            }
            if (!$blocked) {
                $slots[] = ['start' => substr($startTime, 0, 5), 'end' => substr($endTime, 0, 5)];
            }
            $cursor = $cursor->modify('+' . BOOKING_SLOT_MINUTES . ' minutes');
        }
    }
    $unique = [];
    foreach ($slots as $slot) {
        $unique[$slot['start'] . '-' . $slot['end']] = $slot;
    }
    return array_values($unique);
}

function booking_history(PDO $pdo, int $reservationId, int $actorId, string $action, ?string $oldStatus, ?string $newStatus, ?array $oldTerm = null, ?array $newTerm = null): void
{
    query($pdo, 'INSERT INTO reservation_history (reservation_id,actor_id,action,old_status,new_status,old_date,old_start_time,old_end_time,new_date,new_start_time,new_end_time) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
        $reservationId, $actorId, $action, $oldStatus, $newStatus,
        $oldTerm['date'] ?? null, $oldTerm['start'] ?? null, $oldTerm['end'] ?? null,
        $newTerm['date'] ?? null, $newTerm['start'] ?? null, $newTerm['end'] ?? null,
    ]);
}

function booking_create(PDO $pdo, int $userId, int $serviceId, int $employeeId, string $date, string $startTime, string $comment, string $token): int
{
    $date = booking_date($date);
    $startTime = booking_time($startTime);
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        throw new BookingError('Potwierdzenie rezerwacji wygasło. Wybierz termin ponownie.', 409);
    }
    $pdo->beginTransaction();
    try {
        if (!query($pdo, 'SELECT id FROM employees WHERE id=? FOR UPDATE', [$employeeId])->fetchColumn()) {
            throw new BookingError('Pracownik nie istnieje.', 404);
        }
        if (!query($pdo, 'SELECT id FROM services WHERE id=? FOR UPDATE', [$serviceId])->fetchColumn()) {
            throw new BookingError('Usługa nie istnieje.', 404);
        }
        $existing = query($pdo, 'SELECT id,user_id FROM reservations WHERE booking_token=? FOR UPDATE', [$token])->fetch();
        if ($existing) {
            if ((int) $existing['user_id'] !== $userId) {
                throw new BookingError('Nieprawidłowy token rezerwacji.', 403);
            }
            $pdo->commit();
            return (int) $existing['id'];
        }
        $context = booking_context($pdo, $serviceId, $employeeId);
        $slots = booking_available_slots($pdo, $serviceId, $employeeId, $date);
        $selected = null;
        foreach ($slots as $slot) {
            if ($slot['start'] . ':00' === $startTime) {
                $selected = $slot;
                break;
            }
        }
        if (!$selected) {
            throw new BookingError('Ten termin został właśnie zajęty. Wybierz inną godzinę.', 409, 'start_time');
        }
        $comment = trim($comment);
        if (mb_strlen($comment) > 1000) {
            throw new BookingError('Komentarz może mieć najwyżej 1000 znaków.', 422, 'comment');
        }
        query($pdo, "INSERT INTO reservations (user_id,employee_id,service_id,reservation_date,start_time,end_time,status,comment,price_snapshot,booking_token) VALUES (?,?,?,?,?,?,'pending',?,?,?)", [
            $userId, $employeeId, $serviceId, $date, $selected['start'] . ':00', $selected['end'] . ':00', $comment, $context['price'], $token,
        ]);
        $id = (int) $pdo->lastInsertId();
        booking_history($pdo, $id, $userId, 'created', null, 'pending', null, ['date' => $date, 'start' => $selected['start'] . ':00', 'end' => $selected['end'] . ':00']);
        $pdo->commit();
        return $id;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function booking_transitions(string $status): array
{
    return ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['completed', 'cancelled']][$status] ?? [];
}

function booking_change_status(PDO $pdo, int $reservationId, int $actorId, string $newStatus): void
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) { $pdo->beginTransaction(); }
    try {
        $identity = query($pdo, 'SELECT employee_id FROM reservations WHERE id=?', [$reservationId])->fetch();
        if (!$identity) { throw new BookingError('Nie znaleziono rezerwacji.', 404); }
        query($pdo, 'SELECT id FROM employees WHERE id=? FOR UPDATE', [$identity['employee_id']])->fetchColumn();
        $reservation = query($pdo, 'SELECT * FROM reservations WHERE id=? FOR UPDATE', [$reservationId])->fetch();
        if (!$reservation) {
            throw new BookingError('Nie znaleziono rezerwacji.', 404);
        }
        if (!in_array($newStatus, booking_transitions($reservation['status']), true)) {
            throw new BookingError('Niedozwolona zmiana statusu rezerwacji.', 409, 'status');
        }
        if ($newStatus === 'completed') {
            $end = new DateTimeImmutable($reservation['reservation_date'] . ' ' . $reservation['end_time'], new DateTimeZone(BOOKING_TIMEZONE));
            if ($end > booking_now()) {
                throw new BookingError('Przyszłej wizyty nie można oznaczyć jako zrealizowanej.', 409, 'status');
            }
        }
        query($pdo, 'UPDATE reservations SET status=? WHERE id=? AND status=?', [$newStatus, $reservationId, $reservation['status']]);
        booking_history($pdo, $reservationId, $actorId, 'status', $reservation['status'], $newStatus);
        if ($ownsTransaction) { $pdo->commit(); }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function booking_reschedule(PDO $pdo, int $reservationId, int $actorId, string $date, string $startTime): void
{
    $date = booking_date($date);
    $startTime = booking_time($startTime);
    $pdo->beginTransaction();
    try {
        $identity = query($pdo, 'SELECT employee_id,service_id FROM reservations WHERE id=?', [$reservationId])->fetch();
        if (!$identity) { throw new BookingError('Nie znaleziono rezerwacji.', 404); }
        query($pdo, 'SELECT id FROM employees WHERE id=? FOR UPDATE', [$identity['employee_id']])->fetchColumn();
        query($pdo, 'SELECT id FROM services WHERE id=? FOR UPDATE', [$identity['service_id']])->fetchColumn();
        $reservation = query($pdo, 'SELECT * FROM reservations WHERE id=? FOR UPDATE', [$reservationId])->fetch();
        if (!$reservation) {
            throw new BookingError('Nie znaleziono rezerwacji.', 404);
        }
        if (!in_array($reservation['status'], ['pending', 'confirmed'], true)) {
            throw new BookingError('Tej rezerwacji nie można przełożyć.', 409);
        }
        $slots = booking_available_slots($pdo, (int) $reservation['service_id'], (int) $reservation['employee_id'], $date, $reservationId);
        $selected = null;
        foreach ($slots as $slot) {
            if ($slot['start'] . ':00' === $startTime) { $selected = $slot; break; }
        }
        if (!$selected) {
            throw new BookingError('Ten termin został właśnie zajęty. Wybierz inną godzinę.', 409, 'start_time');
        }
        $old = ['date' => $reservation['reservation_date'], 'start' => $reservation['start_time'], 'end' => $reservation['end_time']];
        $new = ['date' => $date, 'start' => $selected['start'] . ':00', 'end' => $selected['end'] . ':00'];
        query($pdo, 'UPDATE reservations SET reservation_date=?,start_time=?,end_time=? WHERE id=?', [$new['date'], $new['start'], $new['end'], $reservationId]);
        booking_history($pdo, $reservationId, $actorId, 'rescheduled', $reservation['status'], $reservation['status'], $old, $new);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}
