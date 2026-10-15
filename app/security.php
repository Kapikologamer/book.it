<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function input(array $data, string $key): string
{
    return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : '';
}

function email_normalize(string $email): string
{
    return strtolower(trim($email));
}

function initials(array $user): string
{
    return mb_strtoupper(mb_substr(trim($user['name']), 0, 1) . mb_substr(trim($user['surname']), 0, 1));
}

function hash_secret(string $secret): string
{
    return password_hash($secret, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}

function pin_secret(string $pin): string
{
    return hash_hmac('sha256', $pin, app_key());
}

function password_errors(array $data): array
{
    $password = input($data, 'password');
    $errors = [];
    // A 72-byte cap also prevents silent truncation in the bcrypt fallback.
    if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
        $errors['password'] = 'Hasło musi mieć minimum 12 znaków i najwyżej 72 bajty.';
    }
    if ($password !== input($data, 'password_confirmation')) {
        $errors['password_confirmation'] = 'Hasła muszą być identyczne.';
    }
    return $errors;
}

function pin_errors(array $data): array
{
    $errors = [];
    if (!preg_match('/\A[0-9]{6}\z/', input($data, 'pin'))) {
        $errors['pin'] = 'PIN musi zawierać dokładnie 6 cyfr (0–9).';
    }
    if (input($data, 'pin') !== input($data, 'pin_confirmation')) {
        $errors['pin_confirmation'] = 'PIN-y muszą być identyczne.';
    }
    return $errors;
}

function profile_errors(array $data): array
{
    $errors = [];
    foreach (['name' => 'Imię', 'surname' => 'Nazwisko'] as $key => $label) {
        $value = trim(input($data, $key));
        if ($value === '' || mb_strlen($value) > 80 || !preg_match("/\A[\p{L}\p{M} '\x{2019}-]+\z/u", $value)) {
            $errors[$key] = $label . ': wpisz od 1 do 80 znaków (litery, spacja, apostrof, łącznik).';
        }
    }
    if (!preg_match('/\A\+?[0-9 ()-]{7,30}\z/', trim(input($data, 'phone')))
        || strlen(preg_replace('/[^0-9]/', '', input($data, 'phone'))) < 7) {
        $errors['phone'] = 'Podaj poprawny numer telefonu (7–30 znaków).';
    }
    return $errors;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_valid(string $token): bool
{
    return isset($_SESSION['csrf']) && $token !== '' && hash_equals($_SESSION['csrf'], $token);
}

function rotate_session(): void
{
    session_regenerate_id(true);
    unset($_SESSION['csrf']);
}

function establish_login(array $user): void
{
    rotate_session();
    unset($_SESSION['recovery']);
    $_SESSION['auth'] = ['id' => (int) $user['id'], 'version' => (int) $user['auth_version'],
        'started' => time(), 'last' => time()];
}

function redirect(string $route): never
{
    header('Location: ./index.php?route=' . rawurlencode($route), true, 303);
    exit;
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function session_boot(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('bookit_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true,
        'secure' => config('APP_ENV') === 'production' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax']);
    session_start();
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
}

function logout(): never
{
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/',
        'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
    redirect('login');
}
