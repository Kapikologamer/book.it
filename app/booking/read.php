<?php
declare(strict_types=1);

function booking_positive_id(array $source, string $key, bool $required = true): ?int
{
    $value = input($source, $key);
    if ($value === '' && !$required) { return null; }
    if (!preg_match('/\A[1-9][0-9]*\z/', $value)) {
        throw new BookingError('Nieprawidłowy identyfikator.', 422, $key);
    }
    return (int) $value;
}

function booking_catalog(PDO $pdo, array $get): array
{
    $q = mb_substr(trim(input($get, 'q')), 0, 120);
    $city = mb_substr(trim(input($get, 'city')), 0, 100);
    $category = booking_positive_id($get, 'category_id', false);
    $page = max(1, (int) (input($get, 'page') ?: 1));
    $where = ["c.active=1", "c.onboarding_status='ready'", "EXISTS (
        SELECT 1 FROM services s JOIN service_categories sc ON sc.id=s.category_id AND sc.active=1
        JOIN employee_services es ON es.service_id=s.id AND es.company_id=s.company_id AND es.active=1
        JOIN employees e ON e.id=es.employee_id AND e.company_id=c.id AND e.active=1
        JOIN users u ON u.id=e.user_id AND u.active=1
        JOIN company_memberships m ON m.user_id=e.user_id AND m.company_id=e.company_id AND m.role='employee' AND m.active=1
        WHERE s.company_id=c.id AND s.active=1 AND EXISTS (SELECT 1 FROM employee_availability a WHERE a.employee_id=e.id)
    )"];
    $params = [];
    if ($q !== '') { $where[] = '(c.name LIKE ? OR c.short_description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    if ($city !== '') { $where[] = 'c.city=?'; $params[] = $city; }
    if ($category) { $where[] = 'EXISTS (SELECT 1 FROM services sx WHERE sx.company_id=c.id AND sx.category_id=? AND sx.active=1)'; $params[] = $category; }
    $base = ' FROM companies c WHERE ' . implode(' AND ', $where);
    $total = (int) query($pdo, 'SELECT COUNT(*)' . $base, $params)->fetchColumn();
    $perPage = 12;
    $page = min($page, max(1, (int) ceil($total / $perPage)));
    $rows = query($pdo, 'SELECT c.*' . $base . ' ORDER BY c.name,c.id LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params)->fetchAll();
    return [
        'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage)),
        'filters' => ['q' => $q, 'city' => $city, 'category_id' => $category],
        'cities' => query($pdo, "SELECT DISTINCT city FROM companies WHERE active=1 AND onboarding_status='ready' AND city<>'' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN),
        'categories' => query($pdo, 'SELECT id,name FROM service_categories WHERE active=1 ORDER BY name')->fetchAll(),
    ];
}

function booking_company(PDO $pdo, int $companyId): array
{
    $company = query($pdo, "SELECT * FROM companies WHERE id=? AND active=1 AND onboarding_status='ready'", [$companyId])->fetch();
    if (!$company) { throw new BookingError('Nie znaleziono dostępnej firmy.', 404); }
    $company['services'] = query($pdo, "SELECT s.*,sc.name category_name,
        EXISTS(SELECT 1 FROM employee_services es JOIN employees e ON e.id=es.employee_id AND e.active=1
          JOIN users u ON u.id=e.user_id AND u.active=1
          JOIN company_memberships m ON m.user_id=e.user_id AND m.company_id=e.company_id AND m.role='employee' AND m.active=1
          WHERE es.service_id=s.id AND es.company_id=s.company_id AND es.active=1 AND EXISTS(SELECT 1 FROM employee_availability a WHERE a.employee_id=e.id)) bookable
        FROM services s JOIN service_categories sc ON sc.id=s.category_id AND sc.active=1
        WHERE s.company_id=? AND s.active=1 ORDER BY sc.name,s.name", [$companyId])->fetchAll();
    return $company;
}

function booking_service(PDO $pdo, int $serviceId): array
{
    $service = query($pdo, "SELECT s.*,sc.name category_name,c.name company_name,c.city FROM services s
        JOIN service_categories sc ON sc.id=s.category_id AND sc.active=1
        JOIN companies c ON c.id=s.company_id AND c.active=1 AND c.onboarding_status='ready'
        WHERE s.id=? AND s.active=1", [$serviceId])->fetch();
    if (!$service) { throw new BookingError('Ta usługa nie jest dostępna.', 404); }
    return $service;
}

function booking_employees(PDO $pdo, int $serviceId): array
{
    $service = booking_service($pdo, $serviceId);
    $service['employees'] = query($pdo, "SELECT e.id,e.description,CONCAT(u.name,' ',u.surname) name
        FROM employee_services es JOIN employees e ON e.id=es.employee_id AND e.company_id=es.company_id AND e.active=1
        JOIN users u ON u.id=e.user_id AND u.active=1
        JOIN company_memberships m ON m.user_id=e.user_id AND m.company_id=e.company_id AND m.role='employee' AND m.active=1
        WHERE es.service_id=? AND es.company_id=? AND es.active=1
          AND EXISTS(SELECT 1 FROM employee_availability a WHERE a.employee_id=e.id)
        ORDER BY u.name,u.surname,e.id", [$serviceId, $service['company_id']])->fetchAll();
    return $service;
}

function booking_reservation_row(PDO $pdo, int $id, ?int $clientId = null, ?int $employeeUserId = null): array
{
    $where = ['r.id=?']; $params = [$id];
    if ($clientId !== null) { $where[] = 'r.user_id=?'; $params[] = $clientId; }
    if ($employeeUserId !== null) { $where[] = 'e.user_id=?'; $params[] = $employeeUserId; }
    $row = query($pdo, "SELECT r.*,s.name service_name,s.duration_minutes,c.id company_id,c.name company_name,
        CONCAT(eu.name,' ',eu.surname) employee_name,CONCAT(cu.name,' ',cu.surname) client_name,cu.phone client_phone
        FROM reservations r JOIN services s ON s.id=r.service_id
        JOIN employees e ON e.id=r.employee_id JOIN users eu ON eu.id=e.user_id
        JOIN users cu ON cu.id=r.user_id JOIN companies c ON c.id=e.company_id
        WHERE " . implode(' AND ', $where), $params)->fetch();
    if (!$row) { throw new BookingError('Nie znaleziono rezerwacji.', 404); }
    $row['history'] = query($pdo, "SELECT h.*,CONCAT(u.name,' ',u.surname) actor_name FROM reservation_history h JOIN users u ON u.id=h.actor_id WHERE h.reservation_id=? ORDER BY h.created_at,h.id", [$id])->fetchAll();
    return $row;
}

function booking_client_list(PDO $pdo, int $userId): array
{
    return query($pdo, "SELECT r.*,s.name service_name,s.duration_minutes,c.name company_name,CONCAT(u.name,' ',u.surname) employee_name
        FROM reservations r JOIN services s ON s.id=r.service_id JOIN employees e ON e.id=r.employee_id
        JOIN users u ON u.id=e.user_id JOIN companies c ON c.id=e.company_id
        WHERE r.user_id=? ORDER BY r.reservation_date DESC,r.start_time DESC,r.id DESC", [$userId])->fetchAll();
}

function booking_employee_context(PDO $pdo, int $userId, ?int $companyId): array
{
    $rows = query($pdo, "SELECT e.id employee_id,e.company_id,c.name company_name FROM employees e
        JOIN companies c ON c.id=e.company_id AND c.active=1 JOIN company_memberships m ON m.user_id=e.user_id AND m.company_id=e.company_id AND m.role='employee' AND m.active=1
        WHERE e.user_id=? AND e.active=1 ORDER BY c.name", [$userId])->fetchAll();
    if (!$rows) { throw new BookingError('Brak aktywnego profilu pracownika.', 403); }
    if ($companyId === null && count($rows) === 1) { return $rows[0] + ['companies' => $rows]; }
    foreach ($rows as $row) { if ((int) $row['company_id'] === $companyId) { return $row + ['companies' => $rows]; } }
    throw new BookingError('Wybierz właściwą firmę.', 403);
}

function booking_employee_list(PDO $pdo, int $employeeId): array
{
    return query($pdo, "SELECT r.*,s.name service_name,CONCAT(u.name,' ',u.surname) client_name
        FROM reservations r JOIN services s ON s.id=r.service_id JOIN users u ON u.id=r.user_id
        WHERE r.employee_id=? ORDER BY r.reservation_date DESC,r.start_time DESC,r.id DESC", [$employeeId])->fetchAll();
}
