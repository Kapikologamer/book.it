<?php
declare(strict_types=1);

require_once __DIR__ . '/domain.php';
require_once __DIR__ . '/read.php';

function management_redirect(string $route, array $params = []): never
{
    header('Location: ./index.php?' . http_build_query(['route' => $route] + $params), true, 303);
    exit;
}

function management_routes(): array
{
    $routes = ['account/invitations', 'onboarding/company', 'admin'];
    foreach (['admin' => ['dashboard', 'companies', 'categories', 'services', 'employees', 'availability', 'time-off', 'users', 'reservations', 'audit', 'profile', 'choose', 'invitations'],
        'company' => ['dashboard', 'profile', 'services', 'employees', 'availability', 'time-off', 'reservations', 'activity', 'choose', 'invitations']] as $prefix => $modules) {
        foreach ($modules as $module) {
            $routes[] = $prefix . '/' . $module;
            if (in_array($module, ['categories', 'services', 'employees', 'availability', 'time-off'], true)) {
                $routes[] = $prefix . '/' . $module . '/edit';
                $routes[] = $prefix . '/' . $module . '/create';
            }
            if (in_array($module, ['services', 'employees', 'reservations', 'companies', 'users'], true)) {
                $routes[] = $prefix . '/' . $module . '/show';
            }
        }
    }
    return $routes;
}

function management_post_fields(string $module, string $action): array
{
    $fields = match ($module . ':' . $action) {
        'categories:save' => ['name', 'description'],
        'services:save' => ['name', 'description', 'category_id', 'price', 'duration_minutes'],
        'employees:save' => ['description', 'service_ids'],
        'employees:invite', 'invitations:invite' => ['email'],
        'availability:save' => ['employee_id', 'day_of_week', 'start_time', 'end_time'],
        'time-off:save' => ['employee_id', 'starts_at', 'ends_at', 'kind', 'reason'],
        'profile:save' => ['name', 'short_description', 'description', 'phone', 'email', 'city', 'address', 'website', 'remove_logo', 'remove_banner'],
        'reservations:status' => ['status', 'confirmed'],
        'users:role' => ['role', 'confirmed'],
        'categories:delete', 'categories:toggle', 'services:delete', 'services:toggle', 'employees:toggle',
        'availability:delete', 'time-off:delete', 'users:toggle', 'companies:toggle', 'invitations:cancel' => ['confirmed'],
        'invitations:accept', 'invitations:decline', 'reservations:export' => [],
        default => throw new ManagementError('Ta operacja nie jest dostępna.', 405),
    };
    return array_merge(['csrf', 'action'], $fields);
}

function management_export(PDO $pdo, array $ctx, array $get): never
{
    $list = management_list_query($ctx, 'reservations', management_filters($get));
    $pdo->beginTransaction();
    try {
        $fresh = current_user($pdo);
        if (!$fresh) {
            throw new ManagementError('Sesja wygasła.', 403);
        }
        management_context($pdo, $fresh, $ctx['admin'], $ctx['company_id']);
        $rows = query($pdo, $list['sql'] . ' LIMIT 10001', $list['params'])->fetchAll();
        if (count($rows) > 10000) {
            throw new ManagementError('Eksport obejmuje najwyżej 10 000 wierszy. Zawęź zakres dat.');
        }
        management_audit($pdo, $ctx, 'reservations', 0, 'export', 'Eksport CSV: ' . count($rows) . ' wierszy.', $ctx['company_id']);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rezerwacje.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    $columns = ['id' => 'ID', 'company_name' => 'Firma', 'client_name' => 'Klient', 'employee_name' => 'Pracownik', 'service_name' => 'Usługa', 'reservation_date' => 'Data', 'start_time' => 'Początek', 'end_time' => 'Koniec', 'status' => 'Status', 'price_snapshot' => 'Cena historyczna PLN (pusta = nieznana)', 'comment' => 'Komentarz'];
    fputcsv($out, array_values($columns), ';', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, array_map(fn ($key) => management_csv_cell($row[$key]), array_keys($columns)), ';', '"', '');
    }
    fclose($out);
    exit;
}

function management_handle(string $route, ?array $user): never
{
    if (!$user) {
        deny(403, 'Zaloguj się, aby otworzyć ten obszar.');
    }
    $errors = [];
    $old = [];
    $personal = $route === 'account/invitations';
    $admin = $route === 'admin' || str_starts_with($route, 'admin/');
    $parts = explode('/', $route);
    $module = $personal ? 'invitations' : ($route === 'onboarding/company' ? 'profile' : ($parts[1] ?? 'dashboard'));
    if ($module === 'activity') {
        $module = 'audit';
    }
    $mode = $parts[2] ?? ($module === 'profile' ? 'edit' : 'list');
    try {
        $companyId = management_id($_GET, 'company_id');
        if ($companyId !== null && in_array($module, ['categories', 'users', 'companies'], true)) {
            throw new ManagementError('Ten moduł nie przyjmuje kontekstu firmy.', 422, 'company_id');
        }
        $ctx = management_context(db(), $user, $admin, $companyId);
        $ctx['personal'] = $personal;
        if ($personal && $companyId !== null) {
            throw new ManagementError('Zaproszenia otwórz w swoim koncie.', 403);
        }
        if (!$admin && !$personal && $companyId === null) {
            $owned = memberships(db(), (int) $user['id'], 'owner');
            if (!$owned) {
                throw new ManagementError('Brak aktywnego członkostwa właściciela.', 403);
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                throw new ManagementError('Wybierz firmę przed zapisaniem formularza.', 403);
            }
            if (count($owned) === 1 && $module !== 'choose') {
                management_redirect($route, ['company_id' => $owned[0]['id']]);
            }
            $module = 'choose';
        }
        if ($admin && $module === 'profile' && !$companyId) {
            $module = 'choose';
        }
        $id = management_id($_GET, 'id', in_array($mode, ['edit', 'show'], true) && $module !== 'profile' && $module !== 'choose');
        if ($mode === 'create' && $id !== null) {
            throw new ManagementError('Formularz dodawania nie przyjmuje ID.', 422);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_valid(input($_POST, 'csrf'))) {
                throw new ManagementError('Formularz wygasł. Otwórz stronę ponownie.', 403);
            }
            $action = input($_POST, 'action');
            $allowed = management_post_fields($module, $action);
            if (array_diff(array_keys($_POST), $allowed) || array_diff(array_keys($_FILES), $module === 'profile' && $action === 'save' ? ['logo', 'banner'] : [])) {
                throw new ManagementError('Formularz zawiera niedozwolone pola.', 403);
            }
            foreach ($_POST as $key => $value) {
                if ($key === 'service_ids') {
                    if (!is_array($value) || count($value) > 500 || count(array_filter($value, 'is_string')) !== count($value)) {
                        throw new ManagementError('Niepoprawna lista usług.', 422, 'service_ids');
                    }
                } elseif (!is_string($value) || strlen($value) > 20000) {
                    throw new ManagementError('Niepoprawna wartość formularza.', 422, $key);
                }
            }
            if ($action === 'export') {
                management_export(db(), $ctx, $_GET);
            }
            db()->beginTransaction();
            try {
                management_mutate(db(), $ctx, $module, $action, $id ?? 0, $_POST, $_FILES);
                management_refresh_readiness(db(), $ctx['company_id']);
                db()->commit();
                if (function_exists('management_media_commit')) {
                    management_media_commit();
                }
            } catch (Throwable $exception) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                if (function_exists('management_media_rollback')) {
                    management_media_rollback();
                }
                throw $exception;
            }
            flash('Zmiany zostały zapisane.');
            if ($route === 'onboarding/company') {
                $ready = query(db(), 'SELECT onboarding_status FROM companies WHERE id = ?', [$companyId])->fetchColumn() === 'ready';
                $target = $ready ? 'company/dashboard' : 'onboarding/company';
            } else {
                $target = $personal ? 'account/invitations' : ($admin ? 'admin/' : 'company/') . ($module === 'audit' && !$admin ? 'activity' : $module);
            }
            management_redirect($target, $companyId ? ['company_id' => $companyId] : []);
        }
        $form = $_SESSION['management_form'] ?? [];
        unset($_SESSION['management_form']);
        if (($form['route'] ?? '') === $route && ($form['company_id'] ?? null) === $companyId && ($form['id'] ?? null) === $id) {
            $errors = $form['errors'];
            $old = $form['old'];
        }
        $management = management_view_data(db(), $ctx, $module, $mode, $id, $_GET);
    } catch (ManagementError $exception) {
        if (in_array($exception->status, [403, 404, 405], true)) {
            deny($exception->status, $exception->getMessage());
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $old = array_intersect_key($_POST, array_flip(['name', 'description', 'category_id', 'price', 'duration_minutes', 'service_ids', 'employee_id', 'day_of_week', 'start_time', 'end_time', 'starts_at', 'ends_at', 'kind', 'reason', 'email', 'short_description', 'phone', 'city', 'address', 'website', 'status']));
            $old = array_filter($old, fn ($value) => is_string($value) || is_array($value));
            $_SESSION['management_form'] = ['route' => $route, 'company_id' => $companyId ?? null, 'id' => $id ?? null, 'errors' => [$exception->field => $exception->getMessage()], 'old' => $old];
            management_redirect($route, array_filter(['company_id' => $companyId ?? null, 'id' => $id ?? null], fn ($v) => $v !== null));
        }
        http_response_code(422);
        $errors = ['form' => $exception->getMessage()];
        if (!isset($ctx)) {
            deny(422, $exception->getMessage());
        }
        $management = management_view_data(db(), $ctx, $module, $mode, $id ?? null, []);
    } catch (PDOException $exception) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array((int) ($exception->errorInfo[1] ?? 0), [1062, 1451, 1452, 1213, 1205], true)) {
            throw $exception;
        }
        $_SESSION['management_form'] = ['route' => $route, 'company_id' => $companyId ?? null, 'id' => $id ?? null, 'errors' => ['form' => 'Nie można zapisać zmiany: rekord jest powiązany, już istnieje lub był równocześnie zmieniany. Odśwież dane i spróbuj ponownie.'], 'old' => []];
        management_redirect($route, array_filter(['company_id' => $companyId ?? null, 'id' => $id ?? null], fn ($v) => $v !== null));
    }
    $titles = ['dashboard' => 'Przegląd', 'companies' => 'Firmy', 'categories' => 'Kategorie', 'services' => 'Usługi', 'employees' => 'Zespół', 'availability' => 'Godziny pracy', 'time-off' => 'Nieobecności', 'users' => 'Użytkownicy', 'reservations' => 'Rezerwacje', 'audit' => 'Dziennik działań', 'profile' => 'Profil firmy', 'choose' => 'Wybierz firmę', 'invitations' => 'Zaproszenia do zespołu'];
    $title = $titles[$module];
    require __DIR__ . '/../views/layout.php';
    exit;
}
