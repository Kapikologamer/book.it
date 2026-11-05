<?php
declare(strict_types=1);
require_once __DIR__ . '/management_components.php';
$GLOBALS['management'] = $management;

$mgContext = $management['ctx'];
$mgAdmin = (bool) $mgContext['admin'];
$mgPersonal = (bool) ($mgContext['personal'] ?? false);
$mgModule = $management['module'];
$mgMode = $management['mode'] ?? 'list';
$mgCompany = $management['company'] ?? [];
$mgOptions = $management['options'] ?? [];
$mgRecord = $management['record'] ?? [];
$mgOld = is_array($old ?? null) ? $old : [];
$mgErrors = is_array($errors ?? null) ? $errors : [];
$mgValues = array_replace($mgModule === 'profile' ? $mgCompany : [], $mgRecord, $mgOld);
$mgFilters = mg_filters();
if (isset($mgContext['company_id'])) $mgFilters['company_id'] = $mgContext['company_id'];
$mgTitles = ['dashboard' => 'Przegląd', 'companies' => 'Firmy', 'categories' => 'Kategorie', 'services' => 'Usługi', 'employees' => 'Zespół', 'availability' => 'Godziny pracy', 'time-off' => 'Nieobecności', 'users' => 'Użytkownicy', 'reservations' => 'Rezerwacje', 'audit' => 'Dziennik działań', 'profile' => 'Profil firmy', 'choose' => 'Wybierz firmę', 'invitations' => $mgPersonal ? 'Moje zaproszenia' : 'Zaproszenia'];
$mgTitle = $mgTitles[$mgModule] ?? 'Zarządzanie';
$mgScoped = in_array($mgModule, ['services', 'employees', 'availability', 'time-off', 'profile'], true);
$mgNeedsChoice = $mgModule === 'choose' || (!$mgPersonal && $mgContext['company_id'] === null && (!$mgAdmin || ($mgScoped && $mgMode === 'create') || $mgModule === 'profile'));
$mgForbidden = !$mgAdmin && in_array($mgModule, ['users', 'companies', 'categories'], true);
$mgEditable = in_array($mgModule, ['categories', 'services', 'employees', 'availability', 'time-off', 'profile'], true);
$mgForm = $mgEditable && (in_array($mgMode, ['create', 'edit'], true) || ($mgModule === 'profile' && $mgMode !== 'show'));
$mgParams = $mgFilters;
if (isset($mgRecord['id']) && $mgModule !== 'profile') $mgParams['id'] = $mgRecord['id'];
if (isset($mgRecord['company_id'])) $mgParams['company_id'] = $mgRecord['company_id'];
$mgGroups = $mgPersonal ? ['Przegląd' => ['invitations']] : [
    'Przegląd' => ['dashboard', 'reservations'],
    'Zarządzanie' => $mgAdmin ? ['companies', 'categories', 'services', 'employees', 'availability', 'time-off', 'profile'] : ['profile', 'services', 'employees', 'availability', 'time-off'],
    'System' => $mgAdmin ? ['users', 'audit'] : ['audit'],
];
?>
<div class="mg-shell">
    <aside class="mg-sidebar">
        <div class="mg-context"><p class="eyebrow"><?= $mgAdmin ? 'Platforma' : 'Twoja przestrzeń' ?></p><strong><?= e($mgModule === 'invitations' ? 'Moje zaproszenia' : ($mgCompany['name'] ?? ($mgAdmin ? 'Administracja' : 'Panel firmy'))) ?></strong><p class="muted"><?= $mgAdmin ? 'Administrator platformy' : ($mgModule === 'invitations' ? 'Konto użytkownika' : 'Zarządzanie firmą') ?></p>
        <?php if ($mgModule !== 'invitations'): ?><a href="<?= e(mg_url('choose', ['company_id' => null])) ?>">Zmień firmę ↗</a><?php endif ?></div>
        <details class="mg-navigation" open><summary>Nawigacja panelu</summary><nav aria-label="Panel zarządzania">
        <?php foreach ($mgGroups as $mgGroup => $mgItems): ?><div class="mg-nav-group"><p><?= e($mgGroup) ?></p><?php foreach ($mgItems as $mgItem): ?><a href="<?= e(mg_url($mgItem)) ?>"<?= $mgModule === $mgItem ? ' aria-current="page"' : '' ?>><?= e($mgTitles[$mgItem]) ?></a><?php endforeach ?></div><?php endforeach ?>
        <a href="<?= e(url('account')) ?>">Ustawienia konta ↗</a></nav></details>
    </aside>
    <section class="mg-main" aria-labelledby="mg-title">
        <nav class="mg-breadcrumbs" aria-label="Ścieżka nawigacji"><a href="<?= e($mgModule === 'invitations' ? url('account') : mg_url('dashboard')) ?>"><?= $mgModule === 'invitations' ? 'Konto' : ($mgAdmin ? 'Administracja' : 'Panel firmy') ?></a><span aria-hidden="true">/</span><a href="<?= e(mg_url($mgModule, $mgFilters)) ?>"><?= e($mgTitle) ?></a><?php if ($mgMode !== 'list'): ?><span aria-hidden="true">/</span><span><?= e(['create' => 'Dodawanie', 'edit' => 'Edycja', 'show' => 'Szczegóły'][$mgMode] ?? '') ?></span><?php endif ?></nav>
        <header class="mg-heading"><div><p class="eyebrow"><?= e($mgCompany['name'] ?? ($mgAdmin ? 'book.it / platforma' : 'book.it / konto')) ?></p><h1 id="mg-title"><?= e($mgNeedsChoice ? 'Wybierz firmę' : $mgTitle) ?></h1><p class="muted"><?= e(match ($mgModule) {
            'dashboard' => 'Cześć, ' . mg_text($mgContext['user']['name'] ?? '') . '. Oto aktualny obraz Twojego obszaru.',
            'services' => 'Oferta, ceny i czas trwania usług w jednym miejscu.',
            'employees' => 'Zarządzaj zespołem, przypisaniami usług i zaproszeniami.',
            'availability' => 'Tygodniowy plan godzin pracy Twojego zespołu.',
            'time-off' => 'Urlopy, przerwy i dni wolne pracowników.',
            'profile' => 'Zadbaj o rozpoznawalność i dane kontaktowe firmy.',
            'reservations' => 'Przeglądaj wizyty i zarządzaj ich statusami.',
            'audit' => 'Historia operacji w wybranym zakresie.',
            'invitations' => 'Zdecyduj, do których zespołów chcesz dołączyć.',
            default => 'Przeglądaj dane i zarządzaj wybranymi rekordami.',
        }) ?></p></div>
        <?php if (!$mgNeedsChoice && !$mgForbidden && $mgMode === 'list' && $mgEditable && $mgModule !== 'profile'): ?><a class="button" href="<?= e(mg_url($mgModule . '/create', array_merge($mgFilters, ['page' => null]))) ?>"><?= $mgModule === 'employees' ? 'Zaproś pracownika' : 'Dodaj rekord' ?></a><?php endif ?></header>

        <?php if ($mgErrors): ?><div class="error-summary" role="alert" tabindex="-1"><strong>Nie udało się zapisać zmian.</strong><ul><?php foreach ($mgErrors as $mgError): ?><li><?= e(mg_text($mgError)) ?></li><?php endforeach ?></ul></div><?php endif ?>

        <?php if ($mgForbidden): ?>
            <div class="mg-card mg-empty"><h2>Moduł administratora platformy</h2><p>Ten obszar nie jest dostępny w panelu firmy.</p></div>
        <?php elseif ($mgNeedsChoice): ?>
            <div class="mg-card"><h2>W jakiej firmie chcesz pracować?</h2><p class="muted">Wybór określi zakres danych i operacji w panelu.</p><ul class="mg-company-list">
            <?php foreach ($management['companies'] ?? [] as $mgChoice): ?><li><a href="<?= e(mg_url($mgModule === 'choose' ? 'dashboard' : $mgModule . ($mgMode === 'create' ? '/create' : ''), ['company_id' => $mgChoice['id']])) ?>"><span class="mg-initials"><?= e(mg_initials($mgChoice['name'])) ?></span><strong><?= e($mgChoice['name']) ?></strong><span aria-hidden="true">→</span></a></li><?php endforeach ?></ul>
            <?php if (empty($management['companies'])): ?><p>Brak firm dostępnych dla tego konta.</p><?php endif ?>
            <?php if ($mgAdmin): ?><a href="<?= e(mg_url('dashboard', ['company_id' => null])) ?>">Przejdź do przeglądu całej platformy</a><?php endif ?></div>
        <?php elseif ($mgModule === 'dashboard'): ?>
            <div class="mg-kpis"><?php foreach ($management['kpis'] ?? [] as $mgLabel => $mgNumber): ?><article class="mg-card mg-kpi"><h2><?= e((string) $mgLabel) ?></h2><strong><?= e(is_numeric($mgNumber) ? number_format((float) $mgNumber, 0, ',', ' ') : '—') ?></strong></article><?php endforeach ?></div>
            <?php if (!empty($management['readiness'])): ?><section class="mg-card"><h2>Gotowość firmy</h2><ul class="mg-readiness"><?php foreach ($management['readiness'] as $mgLabel => $mgReady): ?><li><span class="mg-badge"><?= $mgReady ? 'Gotowe' : 'Do uzupełnienia' ?></span> <?= e((string) $mgLabel) ?></li><?php endforeach ?></ul><div class="mg-row-actions"><a href="<?= e(mg_url('profile')) ?>">Profil firmy</a><a href="<?= e(mg_url('services')) ?>">Oferta</a><a href="<?= e(mg_url('employees')) ?>">Zespół</a><a href="<?= e(mg_url('availability')) ?>">Godziny pracy</a></div></section><?php endif ?>
            <div class="mg-charts"><?php foreach (array_slice($management['charts'] ?? [], 0, 3, true) as $mgChartTitle => $mgChart):
                $mgMax = max(1, ...array_map(static fn ($value): float => is_numeric($value) ? max(0, (float) $value) : 0, array_values($mgChart)));
            ?><section class="mg-card"><h2><?= e((string) $mgChartTitle) ?></h2><?php if (!$mgChart): ?><p class="muted">Brak danych do zestawienia.</p><?php else: ?><dl class="mg-chart"><?php foreach ($mgChart as $mgLabel => $mgNumber): ?><div><dt><?= e((string) $mgLabel) ?></dt><dd><meter min="0" max="<?= e((string) $mgMax) ?>" value="<?= e((string) max(0, (float) $mgNumber)) ?>" aria-label="<?= e((string) $mgLabel) ?>"><?= e(mg_text($mgNumber)) ?></meter><strong><?= e(number_format((float) $mgNumber, 0, ',', ' ')) ?></strong></dd></div><?php endforeach ?></dl><?php endif ?></section><?php endforeach ?></div>
            <section class="mg-card"><h2>Ostatnie rezerwacje i zdarzenia</h2><?php if (empty($management['recent'])): ?><p class="muted">Nie ma jeszcze ostatnich rezerwacji ani zdarzeń.</p><?php else: ?><ul class="mg-recent"><?php foreach ($management['recent'] as $mgRecent): $mgIsAudit = array_key_exists('summary', $mgRecent) || array_key_exists('entity_type', $mgRecent); ?><li><div><strong><?= e(mg_text($mgIsAudit ? ($mgRecent['summary'] ?? $mgRecent['action'] ?? '') : ($mgRecent['service_name'] ?? 'Rezerwacja'))) ?></strong><p class="muted"><?= e($mgIsAudit ? mg_text($mgRecent['actor_name'] ?? '') : mg_text($mgRecent['client_name'] ?? '')) ?> · <?= e(mg_date($mgRecent[$mgIsAudit ? 'created_at' : 'reservation_date'] ?? '', $mgIsAudit)) ?><?= !$mgIsAudit ? ' · ' . e(mg_value('start_time', $mgRecent['start_time'] ?? '')) : '' ?></p></div><?php if (isset($mgRecent['id'])): ?><a href="<?= e(mg_url(($mgIsAudit ? 'audit' : 'reservations') . '/show', ['id' => $mgRecent['id']])) ?>">Szczegóły #<?= e((string) $mgRecent['id']) ?></a><?php endif ?></li><?php endforeach ?></ul><?php endif ?></section>
        <?php elseif ($mgForm): ?>
            <div class="<?= $mgModule === 'profile' ? 'mg-profile-grid' : '' ?>">
            <section class="mg-card"><h2><?= $mgModule === 'employees' && $mgMode === 'create' ? 'Zaproś do zespołu' : ($mgMode === 'create' ? 'Nowy rekord' : 'Edytuj dane') ?></h2><p class="muted">Pola oznaczone * są wymagane.</p>
            <?php mg_post_start($mgModule . ($mgModule === 'profile' ? '' : '/' . $mgMode), $mgModule === 'employees' && $mgMode === 'create' ? 'invite' : 'save', $mgParams, $mgModule === 'profile' ? 'mg-profile-form' : '', $mgModule === 'profile'); ?>
            <?php if (in_array($mgModule, ['categories', 'services', 'profile'], true)): mg_field('name', 'Nazwa', $mgValues, $mgErrors, 'text', ['required' => true, 'maxlength' => $mgModule === 'categories' ? 100 : 120]); endif ?>
            <?php if ($mgModule === 'services'):
                mg_field('category_id', 'Kategoria', $mgValues, $mgErrors, 'text', ['required' => true], mg_options($mgOptions['categories'] ?? []));
                if ($mgMode === 'create' && !array_key_exists('duration_minutes', $mgValues)) $mgValues['duration_minutes'] = 45;
            ?><div class="form-row"><?php mg_field('price', 'Cena (zł)', $mgValues, $mgErrors, 'text', ['required' => true, 'inputmode' => 'decimal', 'maxlength' => 11, 'placeholder' => '0,00']); mg_field('duration_minutes', 'Czas trwania (min)', $mgValues, $mgErrors, 'number', ['required' => true, 'min' => 1, 'max' => 1440, 'step' => 1]); ?></div><?php endif ?>
            <?php if ($mgModule === 'profile'): mg_field('short_description', 'Krótki opis', $mgValues, $mgErrors, 'textarea', ['maxlength' => 255]); endif ?>
            <?php if (in_array($mgModule, ['categories', 'services', 'profile'], true) || ($mgModule === 'employees' && $mgMode !== 'create')): mg_field('description', 'Opis', $mgValues, $mgErrors, 'textarea', ['maxlength' => match ($mgModule) { 'categories' => 500, 'services', 'employees' => 1000, default => 15000 }]); endif ?>
            <?php if ($mgModule === 'employees' && $mgMode === 'create'): mg_field('email', 'E-mail zapraszanej osoby', $mgValues, $mgErrors, 'email', ['required' => true, 'maxlength' => 190, 'autocomplete' => 'email']); ?><p class="muted">Zaproszona osoba przyjmuje zaproszenie po zalogowaniu na swoje konto.</p><?php endif ?>
            <?php if ($mgModule === 'employees' && $mgMode !== 'create'):
                $mgAssigned = array_key_exists('service_ids', $mgOld) ? (array) $mgOld['service_ids'] : ($mgOld ? [] : ($management['assignments'] ?? []));
                $mgAssigned = array_map(static fn ($item): string => (string) (is_array($item) ? ($item['service_id'] ?? '') : $item), $mgAssigned);
            ?><fieldset class="mg-fieldset" id="mg-service_ids"<?= isset($mgErrors['service_ids']) ? ' aria-invalid="true" aria-describedby="mg-service_ids-error"' : '' ?>><legend>Przypisane usługi</legend><p class="muted">Zaznacz wszystkie usługi, które wykonuje pracownik.</p><?php foreach ($mgOptions['services'] ?? [] as $mgService): ?><label class="mg-check"><input type="checkbox" name="service_ids[]" value="<?= e((string) $mgService['id']) ?>"<?= in_array((string) $mgService['id'], $mgAssigned, true) ? ' checked' : '' ?>> <span><?= e($mgService['name']) ?></span></label><?php endforeach ?><?php if (empty($mgOptions['services'])): ?><p>Brak usług do przypisania. <a href="<?= e(mg_url('services/create')) ?>">Dodaj usługę</a>.</p><?php endif ?><?php if (isset($mgErrors['service_ids'])): ?><p class="field-error" id="mg-service_ids-error"><?= e(mg_text($mgErrors['service_ids'])) ?></p><?php endif ?></fieldset><?php endif ?>
            <?php if (in_array($mgModule, ['availability', 'time-off'], true)):
                if (!array_key_exists('employee_id', $mgValues) && isset($mgFilters['employee_id'])) $mgValues['employee_id'] = $mgFilters['employee_id'];
                mg_field('employee_id', 'Pracownik', $mgValues, $mgErrors, 'text', ['required' => true], mg_options($mgOptions['employees'] ?? []));
                if ($mgModule === 'availability'): mg_field('day_of_week', 'Dzień tygodnia', $mgValues, $mgErrors, 'text', ['required' => true], mg_days()); ?><div class="form-row"><?php mg_field('start_time', 'Początek pracy', $mgValues, $mgErrors, 'time', ['required' => true]); mg_field('end_time', 'Koniec pracy', $mgValues, $mgErrors, 'time', ['required' => true]); ?></div>
                <?php else: ?><div class="form-row"><?php mg_field('starts_at', 'Początek nieobecności', $mgValues, $mgErrors, 'datetime-local', ['required' => true]); mg_field('ends_at', 'Koniec nieobecności', $mgValues, $mgErrors, 'datetime-local', ['required' => true]); ?></div><?php mg_field('kind', 'Rodzaj', $mgValues, $mgErrors, 'text', ['required' => true], ['holiday' => 'Urlop', 'break' => 'Przerwa', 'day_off' => 'Dzień wolny']); mg_field('reason', 'Powód (opcjonalnie)', $mgValues, $mgErrors, 'textarea', ['maxlength' => 255]); endif;
            endif ?>
            <?php if ($mgModule === 'profile'): ?><fieldset class="mg-fieldset"><legend>Kontakt i lokalizacja</legend><div class="form-row"><?php mg_field('phone', 'Telefon', $mgValues, $mgErrors, 'tel', ['maxlength' => 30, 'autocomplete' => 'tel']); mg_field('email', 'E-mail firmy', $mgValues, $mgErrors, 'email', ['maxlength' => 190, 'autocomplete' => 'email']); mg_field('city', 'Miejscowość', $mgValues, $mgErrors, 'text', ['maxlength' => 100, 'autocomplete' => 'address-level2']); mg_field('address', 'Adres', $mgValues, $mgErrors, 'text', ['maxlength' => 255, 'autocomplete' => 'street-address']); ?></div><?php mg_field('website', 'Strona internetowa', $mgValues, $mgErrors, 'url', ['maxlength' => 255, 'placeholder' => 'https://']); ?></fieldset>
            <fieldset class="mg-fieldset"><legend>Logo i baner</legend><p class="muted">Formaty: JPEG, PNG lub WebP. Nowe obrazy są zapisywane dopiero po zatwierdzeniu formularza.</p><?php foreach (['logo' => 'Logo firmy', 'banner' => 'Baner firmy'] as $mgImage => $mgLabel): mg_field($mgImage, $mgLabel, [], $mgErrors, 'file', ['accept' => 'image/jpeg,image/png,image/webp']); ?><label class="mg-check"><input type="checkbox" name="remove_<?= e($mgImage) ?>" value="1"<?= !empty($mgValues['remove_' . $mgImage]) ? ' checked' : '' ?>> <span>Usuń <?= $mgImage === 'logo' ? 'logo' : 'baner' ?></span></label><?php endforeach ?></fieldset><?php endif ?>
            <div class="mg-form-actions"><button class="button" type="submit"><?= $mgModule === 'employees' && $mgMode === 'create' ? 'Wyślij zaproszenie' : 'Zapisz zmiany' ?></button><a href="<?= e(mg_url($mgModule === 'profile' ? 'dashboard' : $mgModule, $mgFilters)) ?>">Wróć bez zapisywania</a></div></form></section>
            <?php if ($mgModule === 'profile'): ?><aside class="mg-card mg-preview" aria-label="Podgląd profilu firmy"><p class="eyebrow">Podgląd marki</p><div class="mg-preview-banner"><img data-mg-image="banner" src="<?= e(mg_media($mgValues['banner_path'] ?? '') ?: './index.php?route=media&name=') ?>" alt="Baner firmy"<?= empty($mgValues['banner_path']) || !empty($mgValues['remove_banner']) ? ' hidden' : '' ?>></div><div class="mg-preview-body"><div class="mg-preview-logo"><img data-mg-image="logo" src="<?= e(mg_media($mgValues['logo_path'] ?? '') ?: './index.php?route=media&name=') ?>" alt="Logo firmy"<?= empty($mgValues['logo_path']) || !empty($mgValues['remove_logo']) ? ' hidden' : '' ?>><span data-mg-initials<?= !empty($mgValues['logo_path']) && empty($mgValues['remove_logo']) ? ' hidden' : '' ?>><?= e(mg_initials(mg_text($mgValues['name'] ?? ''))) ?></span></div><h2 data-mg-preview="name"><?= e(mg_text($mgValues['name'] ?? 'Nazwa firmy')) ?></h2><p data-mg-preview="short_description"><?= e(mg_text($mgValues['short_description'] ?? '')) ?></p><p class="muted" data-mg-preview="description"><?= e(mg_text($mgValues['description'] ?? '')) ?></p><dl class="mg-profile-contact"><?php foreach (['city' => 'Miasto', 'address' => 'Adres', 'phone' => 'Telefon', 'email' => 'E-mail', 'website' => 'Strona'] as $mgKey => $mgLabel): ?><div><dt><?= e($mgLabel) ?></dt><dd data-mg-preview="<?= e($mgKey) ?>"><?= e(mg_text($mgValues[$mgKey] ?? '')) ?></dd></div><?php endforeach ?></dl><p class="muted">Podgląd zmian przed zapisaniem.</p></div></aside><?php endif ?></div>
        <?php elseif (in_array($mgMode, ['show', 'edit'], true)): ?>
            <section class="mg-card"><h2>Szczegóły rekordu<?= isset($mgRecord['id']) ? ' #' . e((string) $mgRecord['id']) : '' ?></h2>
            <?php if (!$mgRecord): ?><p>Nie znaleziono rekordu.</p><?php else: ?><dl class="mg-record"><?php foreach (mg_columns($mgModule) as $mgKey => $mgLabel): ?><div><dt><?= e($mgLabel) ?></dt><dd><?= e(mg_value($mgKey, $mgRecord[$mgKey] ?? null)) ?></dd></div><?php endforeach ?></dl>
            <div class="mg-row-actions"><?php if ($mgEditable): ?><a class="button" href="<?= e(mg_url($mgModule . '/edit', $mgParams)) ?>">Edytuj</a><?php endif ?><a href="<?= e(mg_url($mgModule, $mgFilters)) ?>">Wróć do listy</a><?php if ($mgModule === 'employees'): ?><a href="<?= e(mg_url('availability', ['employee_id' => $mgRecord['id']])) ?>">Godziny pracy</a><a href="<?= e(mg_url('time-off', ['employee_id' => $mgRecord['id']])) ?>">Nieobecności</a><?php endif ?><?php if ($mgModule === 'companies'): ?><a href="<?= e(mg_url('profile', ['company_id' => $mgRecord['id']])) ?>">Profil firmy</a><?php endif ?></div>
            <?php if (in_array($mgModule, ['categories', 'services', 'employees', 'users', 'companies'], true)): mg_operation($mgModule, 'toggle', $mgParams, !empty($mgRecord['active']) ? 'Dezaktywuj' : 'Aktywuj', 'Zmienisz aktywność tego rekordu. Historia pozostanie zachowana.'); endif ?>
            <?php if ($mgModule === 'users'): ?><section class="mg-status-form"><h3>Zmień rolę globalną</h3><p class="muted">Rola employee nie zastępuje członkostwa w firmie, a rola admin daje dostęp do całej platformy.</p><?php mg_post_start('users', 'role', $mgParams); mg_field('role', 'Nowa rola', $mgRecord, $mgErrors, 'text', ['required' => true], ['client' => 'Klient', 'employee' => 'Pracownik', 'admin' => 'Administrator']); mg_confirmation('Potwierdzam zmianę globalnych uprawnień tego konta.'); ?><button class="button" type="submit">Zapisz rolę</button></form></section><?php endif ?>
            <?php if (in_array($mgModule, ['categories', 'services', 'availability', 'time-off'], true)): mg_operation($mgModule, 'delete', $mgParams, 'Usuń rekord', 'Usunięcie jest trwałe. Rekord powiązany z innymi danymi może nie zostać usunięty.'); endif ?>
            <?php if ($mgModule === 'reservations'):
                $mgTransitions = match ($mgRecord['status'] ?? '') { 'pending' => ['confirmed' => 'Potwierdzona', 'cancelled' => 'Anulowana'], 'confirmed' => ['completed' => 'Zrealizowana', 'cancelled' => 'Anulowana'], default => [] };
                if ($mgTransitions): ?><section class="mg-status-form"><h3>Zmień status wizyty</h3><?php mg_post_start('reservations', 'status', $mgParams); mg_field('status', 'Nowy status', $mgOld, $mgErrors, 'text', ['required' => true], $mgTransitions); mg_confirmation('Potwierdzam zmianę statusu tej rezerwacji.'); ?><button class="button" type="submit">Zapisz status</button></form></section><?php else: ?><p class="muted">Ta rezerwacja jest zakończona. Zmiana statusu nie jest dostępna.</p><?php endif;
            endif; endif ?></section>
        <?php else: ?>
            <?php if ($mgModule !== 'invitations'): ?><section class="mg-card mg-filters"><h2>Filtry</h2><form method="get" action="./index.php"><input type="hidden" name="route" value="<?= e(($mgAdmin ? 'admin/' : 'company/') . $mgModule) ?>"><div class="mg-filter-grid">
            <?php mg_field('q', 'Szukaj', $mgFilters, [], 'search', ['maxlength' => 200, 'placeholder' => 'Wpisz szukaną frazę']);
                if ($mgAdmin && in_array($mgModule, ['services', 'employees', 'availability', 'time-off', 'reservations', 'audit', 'invitations'], true)): mg_field('company_id', 'Firma', $mgFilters, [], 'text', [], mg_options($management['companies'] ?? [])); elseif (!$mgAdmin && $mgContext['company_id'] !== null): ?><input type="hidden" name="company_id" value="<?= e((string) $mgContext['company_id']) ?>"><?php endif;
                if (in_array($mgModule, ['reservations', 'audit', 'time-off'], true)): mg_field('from', 'Od dnia', $mgFilters, [], 'date'); mg_field('to', 'Do dnia', $mgFilters, [], 'date'); endif;
                if ($mgModule === 'reservations'): mg_field('status', 'Status', $mgFilters, [], 'text', [], mg_statuses()); endif;
                if (in_array($mgModule, ['reservations', 'availability', 'time-off'], true)): mg_field('employee_id', 'Pracownik', $mgFilters, [], 'text', [], mg_options($mgOptions['employees'] ?? [])); endif;
                if (in_array($mgModule, ['categories', 'services', 'employees', 'users', 'companies'], true)): mg_field('active', 'Aktywność', $mgFilters, [], 'text', [], ['1' => 'Aktywne', '0' => 'Nieaktywne']); endif;
                if ($mgModule === 'users'): mg_field('role', 'Rola', $mgFilters, [], 'text', [], ['client' => 'Klient', 'employee' => 'Pracownik', 'admin' => 'Administrator']); endif;
                if ($mgModule === 'audit'): mg_field('action_filter', 'Operacja', $mgFilters, [], 'text', ['maxlength' => 80]); mg_field('actor_id', 'ID autora', $mgFilters, [], 'number', ['min' => 1, 'step' => 1]); endif;
            ?></div><div class="mg-row-actions"><button class="button" type="submit">Zastosuj filtry</button><a href="<?= e(mg_url($mgModule)) ?>">Wyczyść filtry</a></div></form></section><?php endif ?>
            <section class="mg-card mg-results"><div class="mg-section-heading"><h2><?= e($mgTitle) ?></h2><?php if ($mgModule === 'reservations'): mg_post_start($mgModule, 'export', array_merge($mgFilters, ['page' => null])); ?><button class="button secondary" type="submit">Eksportuj CSV</button></form><?php endif ?></div>
            <?php mg_table($mgModule, $management['rows'] ?? [], $mgFilters, true, $mgModule === 'invitations' && !$mgPersonal); mg_pagination($mgModule, (int) ($management['total'] ?? 0), max(1, (int) ($management['page'] ?? 1)), max(1, (int) ($management['per_page'] ?? 25)), $mgFilters); ?></section>
            <?php if ($mgModule === 'employees'): ?><section class="mg-card"><h2>Wysłane zaproszenia</h2><?php mg_table('invitations', $management['invitations'] ?? [], $mgFilters, true, true); ?></section><?php endif ?>
        <?php endif ?>
    </section>
</div>
