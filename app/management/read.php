<?php
declare(strict_types=1);

function management_id(array $data, string $key, bool $required = false): ?int
{
    if (!array_key_exists($key, $data) || $data[$key] === '') {
        if ($required) {
            throw new ManagementError('Wybierz poprawny rekord.', 422, $key);
        }
        return null;
    }
    $value = input($data, $key);
    if (!preg_match('/\A[1-9][0-9]{0,17}\z/', $value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
        throw new ManagementError('Niepoprawny identyfikator.', 422, $key);
    }
    return (int) $value;
}

function management_context(PDO $pdo, array $user, bool $admin, ?int $companyId): array
{
    if ($admin && $user['role'] !== 'admin') {
        throw new ManagementError('Ten obszar wymaga uprawnień administratora platformy.', 403);
    }
    if ($companyId !== null) {
        $company = $admin
            ? query($pdo, 'SELECT * FROM companies WHERE id = ?', [$companyId])->fetch()
            : company_access($pdo, (int) $user['id'], $companyId, 'owner');
        if (!$company) {
            throw new ManagementError('Brak dostępu do tej firmy.', 403);
        }
    }
    return ['user' => $user, 'admin' => $admin, 'company_id' => $companyId];
}

function management_date(string $value, string $field): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Warsaw'));
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new ManagementError('Podaj poprawną datę.', 422, $field);
    }
    return $value;
}

function management_filters(array $get): array
{
    $filters = [];
    foreach (['q', 'from', 'to', 'status', 'active', 'role', 'action_filter'] as $key) {
        if (isset($get[$key]) && !is_string($get[$key])) {
            throw new ManagementError('Niepoprawny filtr.', 422, $key);
        }
        $filters[$key] = trim(input($get, $key));
    }
    if (mb_strlen($filters['q']) > 120 || strlen($filters['action_filter']) > 40) {
        throw new ManagementError('Filtr jest zbyt długi.');
    }
    foreach (['from', 'to'] as $key) {
        if ($filters[$key] !== '') {
            management_date($filters[$key], $key);
        }
    }
    if ($filters['from'] && $filters['to'] && $filters['from'] > $filters['to']) {
        throw new ManagementError('Koniec zakresu nie może poprzedzać początku.', 422, 'to');
    }
    if ($filters['status'] !== '' && !in_array($filters['status'], ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
        throw new ManagementError('Niepoprawny status.', 422, 'status');
    }
    if (!in_array($filters['active'], ['', '0', '1'], true)
        || !in_array($filters['role'], ['', 'client', 'employee', 'admin'], true)) {
        throw new ManagementError('Niepoprawny filtr aktywności lub roli.');
    }
    $filters['employee_id'] = management_id($get, 'employee_id');
    $filters['actor_id'] = management_id($get, 'actor_id');
    return $filters;
}

function management_list_query(array $ctx, string $module, array $filters, ?int $id = null): array
{
    $definitions = [
        'categories' => ['service_categories t', 't.*', null, 't.name', 't.active'],
        'services' => ['services t JOIN companies c ON c.id=t.company_id JOIN service_categories k ON k.id=t.category_id', 't.*,c.name company_name,k.name category_name', 't.company_id', 't.name', 't.active'],
        'employees' => ["employees t JOIN users u ON u.id=t.user_id JOIN companies c ON c.id=t.company_id", "t.*,CONCAT(u.name,' ',u.surname) name,u.email,c.name company_name,(SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') FROM employee_services es JOIN services s ON s.id=es.service_id WHERE es.employee_id=t.id AND es.active=1) service_names", 't.company_id', "CONCAT(u.name,' ',u.surname)", 't.active'],
        'availability' => ["employee_availability t JOIN employees p ON p.id=t.employee_id JOIN users u ON u.id=p.user_id", "t.*,CONCAT(u.name,' ',u.surname) employee_name", 'p.company_id', "CONCAT(u.name,' ',u.surname)", null],
        'time-off' => ["employee_time_off t JOIN employees p ON p.id=t.employee_id JOIN users u ON u.id=p.user_id", "t.*,CONCAT(u.name,' ',u.surname) employee_name", 'p.company_id', 't.reason', null],
        'reservations' => ["reservations t JOIN employee_services es ON es.employee_id=t.employee_id AND es.service_id=t.service_id JOIN companies c ON c.id=es.company_id JOIN services s ON s.id=t.service_id JOIN employees p ON p.id=t.employee_id JOIN users u ON u.id=p.user_id JOIN users cl ON cl.id=t.user_id", "t.*,es.company_id,c.name company_name,CONCAT(cl.name,' ',cl.surname) client_name,CONCAT(u.name,' ',u.surname) employee_name,s.name service_name", 'es.company_id', "CONCAT(cl.name,' ',cl.surname,' ',s.name)", null],
        'companies' => ['companies t', 't.*', 't.id', 't.name', 't.active'],
        'users' => ['users t', 't.id,t.name,t.surname,t.email,t.role,t.active,t.created_at', null, "CONCAT(t.name,' ',t.surname,' ',t.email)", 't.active'],
        'audit' => ["admin_audit_logs t JOIN users u ON u.id=t.actor_id LEFT JOIN companies c ON c.id=t.company_id", "t.*,CONCAT(u.name,' ',u.surname) actor_name,c.name company_name", 't.company_id', 't.summary', null],
        'invitations' => ['employee_invitations t JOIN companies c ON c.id=t.company_id', 't.*,c.name company_name', 't.company_id', 't.email', null],
    ];
    if (!isset($definitions[$module])) {
        throw new ManagementError('Nie znaleziono modułu.', 404);
    }
    if (!$ctx['admin'] && in_array($module, ['categories', 'users', 'companies'], true)) {
        throw new ManagementError('Brak dostępu.', 403);
    }
    [$from, $select, $scope, $search, $active] = $definitions[$module];
    $where = [];
    $params = [];
    if ($module === 'invitations' && ($ctx['personal'] ?? false)) {
        $where[] = 't.email = ?';
        $params[] = $ctx['user']['email'];
    } elseif ($scope && $ctx['company_id']) {
        $where[] = $scope . ' = ?';
        $params[] = $ctx['company_id'];
    } elseif (!$ctx['admin']) {
        throw new ManagementError('Najpierw wybierz swoją firmę.', 403);
    }
    if ($id !== null) {
        $where[] = 't.id = ?';
        $params[] = $id;
    }
    if (($filters['q'] ?? '') !== '') {
        $where[] = $search . ' LIKE ?';
        $params[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
        $where[array_key_last($where)] .= " ESCAPE '!'";
    }
    if ($active && ($filters['active'] ?? '') !== '') {
        $where[] = $active . ' = ?';
        $params[] = $filters['active'];
    }
    if ($module === 'users' && ($filters['role'] ?? '') !== '') {
        $where[] = 't.role = ?';
        $params[] = $filters['role'];
    }
    if ($module === 'reservations' && ($filters['status'] ?? '') !== '') {
        $where[] = 't.status = ?';
        $params[] = $filters['status'];
    }
    if (in_array($module, ['availability', 'time-off', 'reservations'], true) && ($filters['employee_id'] ?? null)) {
        $where[] = 't.employee_id = ?';
        $params[] = $filters['employee_id'];
    }
    if ($module === 'audit') {
        foreach (['actor_id' => 'actor_id', 'action_filter' => 'action'] as $key => $column) {
            if ($filters[$key] ?? null) {
                $where[] = 't.' . $column . ' = ?';
                $params[] = $filters[$key];
            }
        }
    }
    $dateColumn = match ($module) { 'reservations' => 't.reservation_date', 'time-off' => 't.starts_at', 'audit' => 't.created_at', default => null };
    if ($dateColumn) {
        if ($filters['from'] ?? '') {
            $where[] = $dateColumn . ' >= ?';
            $params[] = $filters['from'];
        }
        if ($filters['to'] ?? '') {
            $where[] = $dateColumn . ' < ?';
            $params[] = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
        }
    }
    $suffix = ' FROM ' . $from . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $order = match ($module) { 'reservations' => 't.reservation_date DESC,t.start_time DESC,t.id DESC', 'availability' => 't.day_of_week,t.start_time,t.id', 'time-off' => 't.starts_at DESC,t.id DESC', default => 't.id DESC' };
    return ['sql' => 'SELECT ' . $select . $suffix . ' ORDER BY ' . $order, 'count' => 'SELECT COUNT(*)' . $suffix, 'params' => $params];
}

function management_dashboard(PDO $pdo, array $ctx): array
{
    $cid = $ctx['company_id'];
    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw')))->format('Y-m-d');
    $scope = $cid ? ' WHERE es.company_id = ?' : '';
    $params = $cid ? [$cid] : [];
    $base = ' FROM reservations r JOIN employee_services es ON es.employee_id=r.employee_id AND es.service_id=r.service_id';
    $kpis = [];
    if ($ctx['admin'] && !$cid) {
        $kpis['Konta typu klient (w tym właściciele)'] = query($pdo, "SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();
        $kpis['Firmy / wszystkie'] = query($pdo, 'SELECT COUNT(*) FROM companies')->fetchColumn();
        $kpis['Firmy / aktywne'] = query($pdo, 'SELECT COUNT(*) FROM companies WHERE active=1')->fetchColumn();
    } else {
        $kpis['Klienci z rezerwacją'] = query($pdo, 'SELECT COUNT(DISTINCT r.user_id)' . $base . $scope, $params)->fetchColumn();
    }
    foreach (['employees' => 'Pracownicy', 'services' => 'Usługi'] as $table => $label) {
        $kpis[$label . ' / wszystkie'] = query($pdo, 'SELECT COUNT(*) FROM ' . $table . ($cid ? ' WHERE company_id=?' : ''), $params)->fetchColumn();
        $kpis[$label . ' / aktywne'] = query($pdo, 'SELECT COUNT(*) FROM ' . $table . ' WHERE active=1' . ($cid ? ' AND company_id=?' : ''), $params)->fetchColumn();
    }
    $kpis['Rezerwacje / wszystkie statusy'] = query($pdo, 'SELECT COUNT(*)' . $base . $scope, $params)->fetchColumn();
    $kpis['Rezerwacje na dziś / wszystkie statusy'] = query($pdo, 'SELECT COUNT(*)' . $base . $scope . ($scope ? ' AND' : ' WHERE') . ' r.reservation_date=?', [...$params, $today])->fetchColumn();
    $series = [];
    $start = (new DateTimeImmutable($today))->modify('-6 days');
    for ($i = 0; $i < 7; $i++) {
        $series[$start->modify('+' . $i . ' days')->format('d.m')] = 0;
    }
    foreach (query($pdo, 'SELECT r.reservation_date,COUNT(*) amount' . $base . $scope . ($scope ? ' AND' : ' WHERE') . ' r.reservation_date BETWEEN ? AND ? GROUP BY r.reservation_date', [...$params, $start->format('Y-m-d'), $today])->fetchAll() as $row) {
        $series[(new DateTimeImmutable($row['reservation_date']))->format('d.m')] = (int) $row['amount'];
    }
    $statuses = ['pending' => 'Oczekujące', 'confirmed' => 'Potwierdzone', 'completed' => 'Zrealizowane', 'cancelled' => 'Anulowane'];
    $distribution = array_fill_keys(array_values($statuses), 0);
    foreach (query($pdo, 'SELECT r.status,COUNT(*) amount' . $base . $scope . ' GROUP BY r.status', $params)->fetchAll() as $row) {
        $distribution[$statuses[$row['status']]] = (int) $row['amount'];
    }
    $popular = [];
    foreach (query($pdo, 'SELECT s.id,s.name,COUNT(*) amount' . $base . ' JOIN services s ON s.id=r.service_id' . $scope . " GROUP BY s.id,s.name ORDER BY amount DESC,s.id LIMIT 5", $params)->fetchAll() as $row) {
        $popular[$row['name'] . ' (#' . $row['id'] . ')'] = (int) $row['amount'];
    }
    $list = management_list_query($ctx, 'reservations', []);
    return ['kpis' => $kpis, 'charts' => ['Terminy rezerwacji / ostatnie 7 dni / wszystkie statusy' => $series, 'Statusy wszystkich rezerwacji' => $distribution, 'Najczęściej rezerwowane usługi / wszystkie statusy' => $popular], 'recent' => query($pdo, $list['sql'] . ' LIMIT 5', $list['params'])->fetchAll()];
}

function management_readiness(PDO $pdo, array $company): array
{
    $profile = true;
    foreach (['name', 'short_description', 'phone', 'email', 'city', 'address'] as $field) {
        $profile = $profile && trim((string) ($company[$field] ?? '')) !== '';
    }
    $services = (bool) query($pdo, 'SELECT 1 FROM services s JOIN service_categories k ON k.id=s.category_id WHERE s.company_id=? AND s.active=1 AND k.active=1 LIMIT 1', [$company['id']])->fetchColumn();
    $staff = (bool) query($pdo, 'SELECT 1 FROM employees p JOIN users u ON u.id=p.user_id JOIN company_memberships m ON m.user_id=p.user_id AND m.company_id=p.company_id JOIN employee_services es ON es.employee_id=p.id JOIN services s ON s.id=es.service_id JOIN service_categories k ON k.id=s.category_id JOIN employee_availability a ON a.employee_id=p.id WHERE p.company_id=? AND p.active=1 AND u.active=1 AND m.active=1 AND es.active=1 AND s.active=1 AND k.active=1 LIMIT 1', [$company['id']])->fetchColumn();
    return ['Dane marki i kontakt' => $profile, 'Aktywna usługa w aktywnej kategorii' => $services, 'Aktywny pracownik z usługą i grafikiem' => $staff, 'Firma aktywna' => (bool) $company['active']];
}

function management_view_data(PDO $pdo, array $ctx, string $module, string $mode, ?int $id, array $get): array
{
    $data = ['ctx' => $ctx, 'module' => $module, 'mode' => $mode, 'company' => null, 'companies' => [], 'rows' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'record' => null, 'options' => [], 'assignments' => [], 'invitations' => [], 'readiness' => [], 'kpis' => [], 'charts' => [], 'recent' => []];
    $data['companies'] = $ctx['admin'] ? query($pdo, 'SELECT id,name,active,onboarding_status FROM companies ORDER BY name,id')->fetchAll() : memberships($pdo, (int) $ctx['user']['id'], 'owner');
    if ($ctx['company_id']) {
        $data['company'] = query($pdo, 'SELECT * FROM companies WHERE id=?', [$ctx['company_id']])->fetch();
        $data['readiness'] = management_readiness($pdo, $data['company']);
    }
    if ($module === 'choose') {
        return $data;
    }
    if ($module === 'dashboard') {
        return array_replace($data, management_dashboard($pdo, $ctx));
    }
    if ($module === 'profile') {
        $data['record'] = $data['company'];
        return $data;
    }
    $filters = management_filters($get);
    $page = management_id($get, 'page') ?? 1;
    if ($page > 1000000) {
        throw new ManagementError('Numer strony jest zbyt duży.');
    }
    $list = management_list_query($ctx, $module, $filters);
    $data['total'] = (int) query($pdo, $list['count'], $list['params'])->fetchColumn();
    $data['page'] = min($page, max(1, (int) ceil($data['total'] / 25)));
    $data['rows'] = query($pdo, $list['sql'] . ' LIMIT 25 OFFSET ' . (($data['page'] - 1) * 25), $list['params'])->fetchAll();
    if ($id) {
        $detail = management_list_query($ctx, $module, [], $id);
        $data['record'] = query($pdo, $detail['sql'], $detail['params'])->fetch();
        if (!$data['record']) {
            throw new ManagementError('Nie znaleziono rekordu w tym obszarze.', 404);
        }
    }
    $cid = $ctx['company_id'];
    if ($module === 'services') {
        $data['options']['categories'] = query($pdo, 'SELECT id,name FROM service_categories WHERE active=1 ORDER BY name,id')->fetchAll();
    }
    if (in_array($module, ['employees', 'availability', 'time-off', 'reservations'], true)) {
        $data['options']['employees'] = query($pdo, "SELECT p.id,CONCAT(u.name,' ',u.surname,' · ',c.name) name FROM employees p JOIN users u ON u.id=p.user_id JOIN companies c ON c.id=p.company_id" . ($cid ? ' WHERE p.company_id=?' : '') . ' ORDER BY u.surname,p.id', $cid ? [$cid] : [])->fetchAll();
    }
    if ($module === 'employees') {
        $serviceCid = $data['record']['company_id'] ?? $cid;
        $data['options']['services'] = $serviceCid ? query($pdo, 'SELECT s.id,s.name FROM services s JOIN service_categories k ON k.id=s.category_id WHERE s.company_id=? AND s.active=1 AND k.active=1 ORDER BY s.name,s.id', [$serviceCid])->fetchAll() : [];
        if ($id) {
            $data['assignments'] = array_column(query($pdo, 'SELECT service_id FROM employee_services WHERE employee_id=? AND active=1', [$id])->fetchAll(), 'service_id');
        }
        $invites = management_list_query($ctx, 'invitations', []);
        $data['invitations'] = query($pdo, $invites['sql'] . ' LIMIT 25', $invites['params'])->fetchAll();
    }
    return $data;
}

function management_csv_cell(mixed $value): string
{
    $text = (string) ($value ?? '');
    return preg_match('/\A[\s\x00-\x1F]*[=+\-@]/u', $text) || preg_match('/\A[\t\r\n]/', $text) ? "'" . $text : $text;
}
