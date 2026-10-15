<?php
declare(strict_types=1);

$route = input($_GET, 'route') ?: 'home';
$routes = ['home', 'register/client', 'register/company', 'login', 'recovery', 'reset',
    'account', 'password', 'pin', 'onboarding/client', 'onboarding/company', 'employee', 'admin', 'logout'];
$errors = [];
$old = [];
$user = isset($_SESSION['auth']) ? current_user(db()) : null;
$company = null;
$employeeCompanies = [];

function deny(int $status, string $message): never
{
    http_response_code($status);
    $title = $status === 403 ? 'Brak dostępu' : 'Nie znaleziono strony';
    $route = 'error';
    $user = null;
    require __DIR__ . '/views/layout.php';
    exit;
}

if (!in_array($route, $routes, true)) {
    deny(404, 'Taka strona nie istnieje.');
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    deny(405, 'Niedozwolona metoda.');
}
$protected = ['account', 'password', 'pin', 'onboarding/client', 'onboarding/company', 'employee', 'admin', 'logout'];
if (in_array($route, $protected, true) && !$user) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        deny(403, 'Zaloguj się, aby wykonać tę operację.');
    }
    redirect('login');
}
if ($route === 'admin' && $user['role'] !== 'admin') {
    deny(403, 'Ta strona wymaga uprawnień administratora platformy.');
}
if ($route === 'employee') {
    $employeeCompanies = memberships(db(), (int) $user['id'], 'employee');
    if (!$employeeCompanies) {
        deny(403, 'Brak aktywnego członkostwa pracownika.');
    }
}
if ($route === 'onboarding/client' && ($user['role'] !== 'client' || memberships(db(), (int) $user['id'], 'owner'))) {
    deny(403, 'Ten obszar jest przeznaczony dla konta klienta.');
}
if ($route === 'onboarding/company') {
    $owned = memberships(db(), (int) $user['id'], 'owner');
    $requested = isset($_GET['company_id']) ? filter_var(input($_GET, 'company_id'), FILTER_VALIDATE_INT) : ($owned[0]['id'] ?? 0);
    $company = company_access(db(), (int) $user['id'], (int) $requested, 'owner');
    if (!$company) {
        deny(403, 'Brak dostępu do tej firmy.');
    }
}
if ($user && in_array($route, ['login', 'register/client', 'register/company', 'recovery', 'reset'], true)) {
    redirect(destination(db(), $user));
}
if ($route === 'reset' && !recovery_user(db())) {
    flash('Dostęp do odzyskiwania wygasł lub został wykorzystany.');
    redirect('recovery');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid(input($_POST, 'csrf'))) {
        deny(403, 'Formularz wygasł. Otwórz stronę ponownie i spróbuj jeszcze raz.');
    }
    foreach (['user_id', 'role', 'company_id', 'auth_version', 'active'] as $reserved) {
        if (array_key_exists($reserved, $_POST)) {
            deny(403, 'Formularz zawiera niedozwolone pola.');
        }
    }
    if ($route === 'logout') {
        logout();
    }
    if (in_array($route, ['register/client', 'register/company'], true)) {
        $isCompany = $route === 'register/company';
        $errors = registration_errors($_POST, $isCompany);
        if (!$errors) {
            try {
                $newUser = register_account(db(), $_POST, $isCompany);
                establish_login($newUser);
                redirect($isCompany ? 'onboarding/company' : 'onboarding/client');
            } catch (PDOException $exception) {
                if (($exception->errorInfo[1] ?? null) !== 1062) {
                    throw $exception;
                }
                $errors['email'] = 'Nie można użyć tego adresu. Zaloguj się lub wybierz inny e-mail.';
            }
        }
    } elseif ($route === 'login' || $route === 'recovery') {
        $result = authenticate(db(), input($_POST, 'email'), input($_POST, $route === 'login' ? 'password' : 'pin'), $route === 'recovery');
        if ($result['user']) {
            if ($route === 'recovery') {
                start_recovery($result['user']);
                redirect('reset');
            }
            establish_login($result['user']);
            redirect(destination(db(), $result['user']));
        }
        $errors['form'] = $result['blocked']
            ? ($route === 'login' ? 'Limit prób został wyczerpany. Blokada sesji trwa do jej zakończenia; blokada serwerowa trwa do 15 minut.' : 'Limit prób odzyskiwania został wyczerpany. Spróbuj ponownie za 15 minut.')
            : 'Nieprawidłowe dane lub konto jest niedostępne.';
    } elseif ($route === 'account') {
        $errors = profile_errors($_POST);
        if (!$errors) {
            query(db(), 'UPDATE users SET name = ?, surname = ?, phone = ? WHERE id = ? AND auth_version = ? AND active = 1',
                [trim($_POST['name']), trim($_POST['surname']), trim($_POST['phone']), $user['id'], $user['auth_version']]);
            flash('Dane konta zostały zapisane.');
            redirect('account');
        }
    } elseif ($route === 'password' || $route === 'pin') {
        $errors = $route === 'pin' ? pin_errors($_POST) : password_errors($_POST);
        if (!verify_current_password(db(), $user, input($_POST, 'current_password'))) {
            $errors['current_password'] = 'Nieprawidłowe hasło lub limit prób został wyczerpany.';
        }
        if (!$errors) {
            $hash = hash_secret($route === 'pin' ? pin_secret(input($_POST, 'pin')) : input($_POST, 'password'));
            if (!update_secret(db(), $user, $hash, $route === 'pin')) {
                deny(403, 'Stan konta zmienił się. Zaloguj się ponownie.');
            }
            establish_login(find_user(db(), (int) $user['id']));
            flash($route === 'pin' ? 'PIN zapisany. Pozostałe sesje zostały unieważnione.' : 'Hasło zmienione. Pozostałe sesje zostały unieważnione.');
            redirect('account');
        }
    } elseif ($route === 'reset') {
        $errors = password_errors($_POST);
        if (!$errors) {
            $recovering = recovery_user(db());
            if (!$recovering || !update_secret(db(), $recovering, hash_secret(input($_POST, 'password')))) {
                deny(403, 'Dostęp do odzyskiwania wygasł lub został wykorzystany.');
            }
            unset($_SESSION['recovery'], $_SESSION['auth']);
            rotate_session();
            flash('Nowe hasło zapisane. Zaloguj się nim, aby otworzyć konto.');
            redirect('login');
        }
    } else {
        deny(405, 'Ta strona nie przyjmuje formularzy.');
    }
    // PRG: passwords and PINs never enter the session.
    $old = array_intersect_key($_POST, array_flip(['name', 'surname', 'phone', 'email', 'company_name']));
    $old = array_filter($old, 'is_string');
    foreach ($old as $key => $value) {
        $old[$key] = mb_substr($value, 0, ['name' => 80, 'surname' => 80, 'phone' => 30, 'email' => 190, 'company_name' => 120][$key]);
    }
    $_SESSION['form'] = ['route' => $route, 'errors' => $errors, 'old' => $old];
    redirect($route);
}
if ($route === 'logout') {
    deny(405, 'Wylogowanie wymaga formularza POST.');
}
$form = $_SESSION['form'] ?? [];
unset($_SESSION['form']);
if (($form['route'] ?? '') === $route) {
    $errors = $form['errors'];
    $old = $form['old'];
}
$titles = ['home' => 'Jedno konto. Wiele możliwości.', 'register/client' => 'Załóż konto klienta',
    'register/company' => 'Zarejestruj firmę', 'login' => 'Dobrze Cię widzieć', 'recovery' => 'Odzyskaj dostęp',
    'reset' => 'Ustaw nowe hasło', 'account' => 'Moje konto', 'password' => 'Zmień hasło', 'pin' => 'Ustaw lub zmień PIN',
    'onboarding/client' => 'Twoje miejsce w book.it', 'onboarding/company' => 'Konfiguracja firmy',
    'employee' => 'Obszar pracownika', 'admin' => 'Administrator platformy'];
$title = $titles[$route];
require __DIR__ . '/views/layout.php';
