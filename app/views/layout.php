<?php
declare(strict_types=1);
require_once __DIR__ . '/components.php';
$notice = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="book.it — jedno konto dla klientów i niezależnych firm.">
    <title><?= e($title) ?> · book.it</title>
    <link rel="stylesheet" href="./css/style.css">
    <script src="./js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Przejdź do treści</a>
<header class="site-header"><div class="container header-content">
    <a class="logo" href="./index.php" aria-label="book.it — strona główna">book<span>.it</span></a>
    <nav aria-label="Główna nawigacja">
    <?php if ($user): ?>
        <a href="<?= url(destination(db(), $user)) ?>">Mój obszar</a>
        <a href="<?= url('account') ?>">Moje konto</a>
        <form action="<?= url('logout') ?>" method="post" class="inline-form"><?= csrf_field() ?><button class="link-button">Wyloguj</button></form>
    <?php else: ?>
        <a href="<?= url('login') ?>">Zaloguj się</a>
        <a href="<?= url('register/company') ?>">Dla firm</a>
    <?php endif ?>
    </nav>
</div></header>
<main id="main">
<?php if ($notice): ?><div class="container notice" role="status"><?= e($notice) ?></div><?php endif ?>
<?php if ($route === 'home'): ?>
<section class="hero"><div class="container hero-content">
    <div class="hero-copy"><p class="eyebrow">Klienci i niezależne firmy</p>
        <h1>Twoje plany.<br>Jedno miejsce.</h1>
        <p class="hero-text">Zacznij od konta. Tworzymy miejsce, które połączy Cię z usługami wielu firm — prosto i wygodnie.</p>
        <div class="actions"><a class="button" href="<?= url('register/client') ?>">Załóż konto klienta</a>
        <a class="button secondary" href="<?= url('register/company') ?>">Zarejestruj firmę</a></div>
        <p class="muted">Masz już konto? <a href="<?= url('login') ?>">Zaloguj się</a></p>
    </div>
    <aside class="appointment-card"><p class="eyebrow">Dobry początek</p><h2>Przestrzeń dla Ciebie</h2>
        <ol class="steps"><li><strong>Utwórz konto</strong><span>Jako klient lub właściciel firmy.</span></li>
        <li><strong>Zadbaj o dostęp</strong><span>Ustaw hasło i stały PIN bezpieczeństwa.</span></li>
        <li><strong>Uzupełnij podstawowe dane</strong><span>Edytuj swoje imię, nazwisko i telefon.</span></li></ol>
        <p class="muted">Katalog usług i rezerwowanie terminów pojawią się w kolejnych etapach.</p>
    </aside>
</div></section>
<section class="section container"><div class="section-heading"><p class="eyebrow">book.it</p><h2>Jedna platforma, różne potrzeby.</h2></div>
<div class="feature-grid"><article class="feature-card"><span class="feature-number">01 / KLIENT</span><h3>Własne konto</h3><p>Twoje dane i ustawienia bezpieczeństwa w jednym miejscu.</p></article>
<article class="feature-card"><span class="feature-number">02 / FIRMA</span><h3>Własna przestrzeń</h3><p>Konto właściciela połączone z firmą. Pełna konfiguracja działalności jest w przygotowaniu.</p></article>
<article class="feature-card"><span class="feature-number">03 / ZESPÓŁ</span><h3>Dostęp pracownika</h3><p>Osobny obszar dostępny członkom zespołu przypisanym do firmy.</p></article></div></section>
<?php elseif ($route === 'error'): ?>
<section class="page container"><h1><?= e($title) ?></h1><p><?= e($message) ?></p><a class="button" href="./index.php">Strona główna</a></section>
<?php elseif (in_array($route, ['register/client','register/company','login','recovery','reset','account','password','pin'], true)): ?>
<section class="form-shell container"><div class="form-intro"><p class="eyebrow"><?= $user ? 'Ustawienia konta' : 'Witaj w book.it' ?></p>
<h1><?= e($title) ?></h1>
<?php if ($route === 'recovery'): ?>
<p class="muted">Zmiana hasła jest dostępna po zalogowaniu. Jeśli nie pamiętasz hasła, użyj PIN-u bezpieczeństwa, aby odzyskać dostęp do konta.</p>
<p>Poprawny PIN otwiera tylko tryb ustawienia nowego hasła na 5 minut. Pełne konto będzie dostępne po zwykłym zalogowaniu.</p>
<?php elseif ($route === 'reset'): ?><p>Ustaw nowe hasło. Po zapisaniu zaloguj się ponownie.</p>
<?php elseif ($route === 'register/company'): ?><p>Zacznij od podstaw. Dane działalności, logo i ofertę uzupełnisz w kolejnych etapach.</p>
<?php elseif ($route === 'register/client'): ?><p>Jedno konto dla wielu firm. Na początek potrzebujemy kilku podstawowych danych.</p>
<?php elseif ($route === 'account'): ?><div class="identity"><span class="avatar"><?= e(initials($user)) ?></span><div><strong><?= e($user['name'] . ' ' . $user['surname']) ?></strong><p><?= e($user['email']) ?></p></div></div>
<p>E-mail jest identyfikatorem konta. W tym etapie edytujesz imię, nazwisko i telefon.</p>
<div class="actions"><a href="<?= url('password') ?>">Zmień hasło</a><a href="<?= url('pin') ?>"><?= $user['pin_hash'] ? 'Zmień PIN' : 'Ustaw PIN bezpieczeństwa' ?></a></div>
<?php elseif ($route === 'pin'): ?><p>Stały, sześciocyfrowy PIN służy do odzyskiwania dostępu. To nie jest kod jednorazowy.</p>
<?php else: ?><p><?= $route === 'login' ? 'Zaloguj się do swojego konta klienta, firmy lub pracownika.' : 'Potwierdź obecne hasło, aby zabezpieczyć tę zmianę.' ?></p><?php endif ?>
</div><div class="form-card">
<?php if ($errors): ?><div class="error-summary" role="alert" tabindex="-1"><strong>Sprawdź formularz.</strong>
<ul><?php foreach ($errors as $key => $error): ?><li><?php if ($key !== 'form'): ?><a href="#<?= e($key) ?>"><?= e($error) ?></a><?php else: ?><?= e($error) ?><?php endif ?></li><?php endforeach ?></ul></div><?php endif ?>
<form action="<?= url($route) ?>" method="post">
<?= csrf_field() ?>
<?php if ($route === 'register/company'): field('company_name', 'Nazwa firmy', 'text', $old, $errors, 'organization', 120); endif ?>
<?php if (in_array($route, ['register/client','register/company','account'], true)): ?>
<div class="form-row"><?php field('name', 'Imię', 'text', $old ?: ($user ?? []), $errors, 'given-name', 80); field('surname', 'Nazwisko', 'text', $old ?: ($user ?? []), $errors, 'family-name', 80); ?></div>
<?php field('phone', 'Numer telefonu', 'tel', $old ?: ($user ?? []), $errors, 'tel', 30); endif ?>
<?php if (in_array($route, ['register/client','register/company','login','recovery'], true)): field('email', 'Adres e-mail', 'email', $old, $errors, 'username', 190); endif ?>
<?php if ($route === 'password' || $route === 'pin'): field('current_password', 'Obecne hasło', 'password', [], $errors, 'current-password', 72); endif ?>
<?php if (in_array($route, ['register/client','register/company','password','reset','login'], true)):
field('password', $route === 'login' ? 'Hasło' : 'Nowe hasło (minimum 12 znaków)', 'password', [], $errors, $route === 'login' ? 'current-password' : 'new-password', 72);
if ($route !== 'login'): field('password_confirmation', 'Powtórz hasło', 'password', [], $errors, 'new-password', 72); endif;
endif ?>
<?php if (in_array($route, ['register/client','register/company','pin','recovery'], true)):
pin_field('pin', 'PIN bezpieczeństwa', $errors);
if ($route !== 'recovery'): pin_field('pin_confirmation', 'Powtórz PIN', $errors); endif;
endif ?>
<button class="button full-width" type="submit"><?= e(match ($route) {
    'register/client' => 'Utwórz konto klienta', 'register/company' => 'Utwórz konto firmy',
    'login' => 'Zaloguj się', 'recovery' => 'Zweryfikuj PIN', 'reset' => 'Zapisz nowe hasło',
    default => 'Zapisz zmiany'
}) ?></button>
</form>
<?php if ($route === 'login'): ?><p><a href="<?= url('recovery') ?>">Nie pamiętam hasła?</a></p><p class="muted">Nie masz konta? <a href="<?= url('register/client') ?>">Zarejestruj się</a></p>
<?php elseif (!$user): ?><p class="muted"><a href="<?= url('login') ?>">Wróć do logowania</a></p><?php endif ?>
</div></section>
<?php else: ?>
<section class="page container"><p class="eyebrow">Twój obszar</p><h1><?= e($title) ?></h1>
<div class="identity"><span class="avatar"><?= e(initials($user)) ?></span><div><h2>Cześć, <?= e($user['name']) ?>!</h2><p class="muted"><?= e($user['email']) ?></p></div></div>
<?php if (!$user['pin_hash']): ?><p class="notice">Twoje konto nie ma jeszcze PIN-u. <a href="<?= url('pin') ?>">Ustaw go po potwierdzeniu hasła</a>, aby umożliwić odzyskiwanie dostępu.</p><?php endif ?>
<?php if ($route === 'onboarding/company'): ?>
<article class="feature-card"><p class="eyebrow"><?= $company['onboarding_status'] === 'pending' ? 'Konfiguracja firmy nieukończona' : 'Konfiguracja ukończona' ?></p><h2><?= e($company['name']) ?></h2>
<p>W kolejnych etapach pojawią się: logo, baner, opis, dane firmy, pracownicy, usługi i dostępność.</p>
<p class="muted">Ta strona pokazuje stan Twojej firmy. Formularze pełnej konfiguracji są w przygotowaniu.</p></article>
<?php elseif ($route === 'onboarding/client'): ?>
<article class="feature-card"><h2>Nick / avatar — konfiguracja w przygotowaniu</h2><p>Na razie Twoim avatarem są inicjały. Podstawowe dane możesz już zmieniać w ustawieniach konta.</p></article>
<?php elseif ($route === 'employee'): ?>
<div class="feature-card"><h2>Twój zespół</h2><ul><?php foreach ($employeeCompanies as $item): ?><li><?= e($item['name']) ?></li><?php endforeach ?></ul><p>Grafik i obsługa rezerwacji pojawią się w kolejnych etapach.</p></div>
<?php elseif ($route === 'admin'): ?>
<div class="feature-card"><h2>Dostęp administratora platformy</h2><p>Twoje uprawnienie zostało zweryfikowane. Narzędzia administracyjne będą dodane w kolejnym etapie.</p></div>
<?php endif ?>
<p><a class="button" href="<?= url('account') ?>">Przejdź do ustawień konta</a></p>
</section>
<?php endif ?>
</main>
<footer class="site-footer"><div class="container footer-content"><a class="logo" href="./index.php">book<span>.it</span></a><p>Twoje plany, jedno miejsce · <span id="current-year">2026</span></p></div></footer>
</body></html>
