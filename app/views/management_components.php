<?php
declare(strict_types=1);

require_once __DIR__ . '/components.php';

function mg_text(mixed $value): string
{
    return is_scalar($value) ? (string) $value : '';
}

function mg_filters(): array
{
    $filters = [];
    foreach (['q', 'from', 'to', 'status', 'employee_id', 'company_id', 'active', 'role', 'action_filter', 'actor_id', 'page'] as $key) {
        if (isset($_GET[$key]) && is_scalar($_GET[$key]) && (string) $_GET[$key] !== '') {
            $filters[$key] = (string) $_GET[$key];
        }
    }
    return $filters;
}

function mg_url(string $module, array $params = []): string
{
    global $management;
    $personal = ($management['ctx']['personal'] ?? false) && $module === 'invitations';
    $routeModule = $module === 'audit' && !($management['ctx']['admin'] ?? false) ? 'activity' : $module;
    $route = $personal ? 'account/invitations' : (($management['ctx']['admin'] ?? false) ? 'admin/' : 'company/') . $routeModule;
    $query = array_merge(['company_id' => $management['ctx']['company_id'] ?? null], $params);
    unset($query['route']);
    if ($personal) unset($query['company_id']);
    if (($management['ctx']['admin'] ?? false) && in_array(explode('/', $module, 2)[0], ['categories', 'users', 'companies'], true)) unset($query['company_id']);
    if ($module === 'profile') unset($query['id']);
    return './index.php?' . http_build_query(['route' => $route] + $query, '', '&', PHP_QUERY_RFC3986);
}

function mg_options(array $rows): array
{
    $options = [];
    foreach ($rows as $row) {
        $options[(string) $row['id']] = mg_text($row['name'] ?? $row['email'] ?? $row['id']);
    }
    return $options;
}

function mg_statuses(): array
{
    return ['pending' => 'Oczekująca', 'confirmed' => 'Potwierdzona', 'completed' => 'Zrealizowana', 'cancelled' => 'Anulowana'];
}

function mg_days(): array
{
    return [1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek', 5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela'];
}

function mg_date(mixed $value, bool $time = false): string
{
    $value = mg_text($value);
    if ($value === '') return '—';
    try {
        return (new DateTimeImmutable($value))->format($time ? 'd.m.Y H:i' : 'd.m.Y');
    } catch (Exception) {
        return $value;
    }
}

function mg_value(string $key, mixed $value): string
{
    if ($value === null || $value === '') return '—';
    if (is_array($value)) return implode(', ', array_map('mg_text', $value));
    return match ($key) {
        'price', 'price_snapshot' => is_numeric($value) ? number_format((float) $value, 2, ',', ' ') . ' zł' : mg_text($value),
        'active' => (bool) $value ? 'Aktywny' : 'Nieaktywny',
        'duration_minutes' => mg_text($value) . ' min',
        'day_of_week' => mg_days()[(int) $value] ?? mg_text($value),
        'reservation_date' => mg_date($value),
        'starts_at', 'ends_at', 'created_at', 'expires_at' => mg_date($value, true),
        'start_time', 'end_time' => substr(mg_text($value), 0, 5),
        'status' => (mg_statuses() + ['accepted' => 'Przyjęte', 'declined' => 'Odrzucone', 'expired' => 'Wygasłe'])[$value] ?? mg_text($value),
        'role', 'actor_role' => ['admin' => 'Administrator', 'owner' => 'Właściciel', 'employee' => 'Pracownik', 'client' => 'Klient'][$value] ?? mg_text($value),
        'kind' => ['holiday' => 'Urlop', 'break' => 'Przerwa', 'day_off' => 'Dzień wolny'][$value] ?? mg_text($value),
        'onboarding_status' => ['pending' => 'W konfiguracji', 'ready' => 'Gotowa'][$value] ?? mg_text($value),
        default => mg_text($value),
    };
}

function mg_field(string $name, string $label, array $values, array $errors, string $type = 'text', array $attributes = [], ?array $options = null): void
{
    $id = 'mg-' . $name;
    $value = mg_text($values[$name] ?? '');
    $error = mg_text($errors[$name] ?? '');
    $attrs = '';
    foreach ($attributes as $key => $attribute) {
        if ($attribute === false || $attribute === null) continue;
        $attrs .= ' ' . e($key) . ($attribute === true ? '' : '="' . e(mg_text($attribute)) . '"');
    }
    ?>
    <div class="field mg-field">
        <label for="<?= e($id) ?>"><?= e($label) ?><?= !empty($attributes['required']) ? ' *' : '' ?></label>
        <?php if ($options !== null): ?>
            <select id="<?= e($id) ?>" name="<?= e($name) ?>" aria-invalid="<?= $error !== '' ? 'true' : 'false' ?>"<?= $error !== '' ? ' aria-describedby="' . e($id) . '-error"' : '' ?><?= $attrs ?>>
                <option value=""><?= !empty($attributes['required']) ? 'Wybierz…' : 'Wszystkie' ?></option>
                <?php foreach ($options as $key => $text): ?><option value="<?= e((string) $key) ?>"<?= (string) $key === $value ? ' selected' : '' ?>><?= e(mg_text($text)) ?></option><?php endforeach ?>
            </select>
        <?php elseif ($type === 'textarea'): ?>
            <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="4" aria-invalid="<?= $error !== '' ? 'true' : 'false' ?>"<?= $error !== '' ? ' aria-describedby="' . e($id) . '-error"' : '' ?><?= $attrs ?>><?= e($value) ?></textarea>
        <?php else: ?>
            <input id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>"<?= $type !== 'file' ? ' value="' . e($type === 'datetime-local' ? str_replace(' ', 'T', $value) : $value) . '"' : '' ?> aria-invalid="<?= $error !== '' ? 'true' : 'false' ?>"<?= $error !== '' ? ' aria-describedby="' . e($id) . '-error"' : '' ?><?= $attrs ?>>
        <?php endif ?>
        <?php if ($error !== ''): ?><p class="field-error" id="<?= e($id) ?>-error"><?= e($error) ?></p><?php endif ?>
    </div>
    <?php
}

function mg_post_start(string $module, string $action, array $params = [], string $class = '', bool $upload = false): void
{
    ?><form method="post" action="<?= e(mg_url($module, $params)) ?>" class="<?= e($class) ?>"<?= $upload ? ' enctype="multipart/form-data"' : '' ?>><?= csrf_field() ?><input type="hidden" name="action" value="<?= e($action) ?>"><?php
}

function mg_confirmation(string $label): void
{
    ?><label class="mg-check"><input type="checkbox" name="confirmed" value="1" required> <span><?= e($label) ?></span></label><?php
}

function mg_operation(string $module, string $action, array $params, string $label, string $explanation): void
{
    ?><details class="mg-operation"><summary><?= e($label) ?></summary><?php mg_post_start($module, $action, $params); ?><p><?= e($explanation) ?></p><?php mg_confirmation('Potwierdzam: ' . mb_strtolower($label) . '.'); ?><button class="button mg-danger" type="submit"><?= e($label) ?></button></form></details><?php
}

function mg_columns(string $module): array
{
    return match ($module) {
        'categories' => ['id' => 'ID', 'name' => 'Nazwa', 'description' => 'Opis', 'active' => 'Aktywność'],
        'services' => ['id' => 'ID', 'name' => 'Usługa', 'description' => 'Opis', 'company_name' => 'Firma', 'category_name' => 'Kategoria', 'price' => 'Cena', 'duration_minutes' => 'Czas', 'active' => 'Aktywność'],
        'employees' => ['id' => 'ID', 'name' => 'Pracownik', 'email' => 'E-mail', 'description' => 'Opis', 'service_names' => 'Usługi', 'active' => 'Aktywność'],
        'availability' => ['id' => 'ID', 'employee_name' => 'Pracownik', 'day_of_week' => 'Dzień', 'start_time' => 'Od', 'end_time' => 'Do'],
        'time-off' => ['id' => 'ID', 'employee_name' => 'Pracownik', 'starts_at' => 'Początek', 'ends_at' => 'Koniec', 'kind' => 'Rodzaj', 'reason' => 'Powód'],
        'reservations' => ['id' => 'ID', 'company_name' => 'Firma', 'client_name' => 'Klient', 'employee_name' => 'Pracownik', 'service_name' => 'Usługa', 'reservation_date' => 'Data wizyty', 'start_time' => 'Od', 'end_time' => 'Do', 'status' => 'Status', 'price_snapshot' => 'Cena wizyty', 'comment' => 'Komentarz'],
        'users' => ['id' => 'ID', 'name' => 'Imię', 'surname' => 'Nazwisko', 'email' => 'E-mail', 'role' => 'Rola', 'active' => 'Aktywność'],
        'companies' => ['id' => 'ID', 'name' => 'Firma', 'city' => 'Miasto', 'onboarding_status' => 'Konfiguracja', 'active' => 'Aktywność'],
        'audit' => ['id' => 'ID', 'actor_name' => 'Autor', 'actor_role' => 'Rola', 'company_name' => 'Firma', 'entity_type' => 'Obiekt', 'entity_id' => 'ID obiektu', 'action' => 'Operacja', 'summary' => 'Podsumowanie', 'created_at' => 'Data operacji'],
        'invitations' => ['id' => 'ID', 'company_name' => 'Firma', 'email' => 'E-mail', 'status' => 'Status', 'expires_at' => 'Ważne do'],
        default => [],
    };
}

function mg_table(string $module, array $rows, array $filters = [], bool $actions = true, bool $outgoing = false): void
{
    global $management;
    if (!$rows) {
        ?><div class="mg-empty"><h3>Brak wyników</h3><p>Nie ma jeszcze danych lub żaden rekord nie pasuje do wybranych filtrów.</p></div><?php
        return;
    }
    $columns = mg_columns($module);
    if (!($management['ctx']['admin'] ?? false) && $module !== 'invitations') unset($columns['company_name']);
    ?>
    <div class="mg-table-scroll" role="region" aria-label="Tabela danych — można przewijać poziomo" tabindex="0">
    <table class="mg-table"><caption><?= $outgoing ? 'Wysłane zaproszenia' : 'Lista rekordów' ?> · <?= count($rows) ?> na tej stronie</caption><thead><tr>
    <?php foreach ($columns as $label): ?><th scope="col"><?= e($label) ?></th><?php endforeach ?>
    <?php if ($actions): ?><th scope="col">Działania</th><?php endif ?></tr></thead><tbody>
    <?php foreach ($rows as $row):
        $params = array_merge($filters, ['id' => $row['id']]);
        if (isset($row['company_id'])) $params['company_id'] = $row['company_id'];
    ?><tr>
        <?php foreach ($columns as $key => $label): ?><td><?php if (in_array($key, ['active', 'status', 'onboarding_status'], true)): ?><span class="mg-badge"><?= e(mg_value($key, $row[$key] ?? null)) ?></span><?php else: ?><?= e(mg_value($key, $row[$key] ?? null)) ?><?php endif ?></td><?php endforeach ?>
        <?php if ($actions): ?><td><div class="mg-row-actions">
        <?php if ($module === 'invitations'):
            $pending = ($row['status'] ?? '') === 'pending';
            $expiry = strtotime(mg_text($row['expires_at'] ?? ''));
            if ($pending && $expiry !== false && $expiry > time()):
                if ($outgoing): mg_operation('invitations', 'cancel', $params, 'Anuluj zaproszenie', 'Zaproszona osoba nie będzie mogła przyjąć tego zaproszenia.');
                else: foreach (['accept' => 'Przyjmij', 'decline' => 'Odrzuć'] as $action => $label): mg_post_start('invitations', $action, $params); ?><button class="button <?= $action === 'decline' ? 'secondary' : '' ?>" type="submit"><?= e($label) ?></button></form><?php endforeach; endif;
            elseif ($pending): ?><span class="muted">Zaproszenie wygasło</span><?php endif;
        else: ?>
            <?php if (in_array($module, ['services', 'employees', 'reservations', 'companies', 'users'], true)): ?><a href="<?= e(mg_url($module . '/show', $params)) ?>" aria-label="Szczegóły rekordu <?= e((string) $row['id']) ?>">Szczegóły</a><?php endif ?>
            <?php if (in_array($module, ['categories', 'services', 'employees', 'availability', 'time-off'], true)): ?><a href="<?= e(mg_url($module . '/edit', $params)) ?>" aria-label="Edytuj rekord <?= e((string) $row['id']) ?>">Edytuj</a><?php endif ?>
            <?php if (in_array($module, ['categories', 'availability', 'time-off'], true)): mg_operation($module, 'delete', $params, 'Usuń rekord', 'Usunięcie rekordu jest trwałe.'); endif ?>
            <?php if ($module === 'categories'): mg_operation($module, 'toggle', $params, !empty($row['active']) ? 'Dezaktywuj' : 'Aktywuj', 'Zmienisz dostępność tej kategorii.'); endif ?>
            <?php if ($module === 'companies'): ?><a href="<?= e(mg_url('dashboard', ['company_id' => $row['id']])) ?>">Otwórz firmę</a><?php endif ?>
        <?php endif ?></div></td><?php endif ?>
    </tr><?php endforeach ?></tbody></table></div>
    <?php
}

function mg_pagination(string $module, int $total, int $page, int $perPage, array $filters): void
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    ?><nav class="mg-pagination" aria-label="Strony wyników"><span><?= number_format($total, 0, ',', ' ') ?> wyników · strona <?= $page ?> z <?= $pages ?></span><div class="mg-row-actions">
    <?php if ($page > 1): ?><a href="<?= e(mg_url($module, array_merge($filters, ['page' => $page - 1]))) ?>">← Poprzednia</a><?php endif ?>
    <?php foreach (array_unique([1, max(1, $page - 1), $page, min($pages, $page + 1), $pages]) as $number): ?><a href="<?= e(mg_url($module, array_merge($filters, ['page' => $number]))) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= $number ?></a><?php endforeach ?>
    <?php if ($page < $pages): ?><a href="<?= e(mg_url($module, array_merge($filters, ['page' => $page + 1]))) ?>">Następna →</a><?php endif ?></div></nav><?php
}

function mg_media(mixed $path): string
{
    $name = basename(str_replace('\\', '/', mg_text($path)));
    return $name !== '' ? './index.php?route=media&name=' . rawurlencode($name) : '';
}

function mg_initials(string $name): string
{
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return mb_strtoupper(implode('', array_map(static fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2)))) ?: 'BI';
}
