<?php
declare(strict_types=1);

function query(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function find_user(PDO $pdo, int $id): ?array
{
    return query($pdo, 'SELECT * FROM users WHERE id = ?', [$id])->fetch() ?: null;
}

function current_user(PDO $pdo): ?array
{
    $auth = $_SESSION['auth'] ?? null;
    if (!$auth) {
        return null;
    }
    $user = find_user($pdo, $auth['id']);
    if (!$user || !$user['active'] || (int) $user['auth_version'] !== $auth['version']
        || time() - $auth['last'] > 1800 || time() - $auth['started'] > 28800) {
        unset($_SESSION['auth']);
        rotate_session();
        flash('Sesja wygasła. Zaloguj się ponownie.');
        return null;
    }
    $_SESSION['auth']['last'] = time();
    return $user;
}

function memberships(PDO $pdo, int $userId, string $role): array
{
    return query($pdo, 'SELECT c.*, m.role FROM companies c JOIN company_memberships m ON m.company_id = c.id
        WHERE m.user_id = ? AND m.role = ? AND m.active = 1 AND c.active = 1 ORDER BY c.id', [$userId, $role])->fetchAll();
}

function company_access(PDO $pdo, int $userId, int $companyId, string $role): ?array
{
    return query($pdo, 'SELECT c.*, m.role FROM companies c JOIN company_memberships m ON m.company_id = c.id
        WHERE m.user_id = ? AND c.id = ? AND m.role = ? AND m.active = 1 AND c.active = 1',
        [$userId, $companyId, $role])->fetch() ?: null;
}

function destination(PDO $pdo, array $user): string
{
    if ($user['role'] === 'admin') {
        return 'admin';
    }
    if (memberships($pdo, (int) $user['id'], 'owner')) {
        return 'onboarding/company';
    }
    if (memberships($pdo, (int) $user['id'], 'employee')) {
        return 'employee';
    }
    return 'onboarding/client';
}

function registration_errors(array $data, bool $company): array
{
    $errors = profile_errors($data) + password_errors($data) + pin_errors($data);
    $email = email_normalize(input($data, 'email'));
    if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Podaj poprawny adres e-mail (maksymalnie 190 znaków).';
    }
    $name = trim(input($data, 'company_name'));
    if ($company && (mb_strlen($name) < 2 || mb_strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F]/', $name))) {
        $errors['company_name'] = 'Nazwa firmy musi mieć 2–120 znaków bez znaków sterujących.';
    }
    return $errors;
}

function register_account(PDO $pdo, array $data, bool $company): array
{
    $passwordHash = hash_secret(input($data, 'password'));
    $pinHash = hash_secret(pin_secret(input($data, 'pin')));
    $pdo->beginTransaction();
    try {
        query($pdo, 'INSERT INTO users (name, surname, email, phone, password, pin_hash) VALUES (?, ?, ?, ?, ?, ?)',
            [trim($data['name']), trim($data['surname']), email_normalize($data['email']), trim($data['phone']), $passwordHash, $pinHash]);
        $id = (int) $pdo->lastInsertId();
        if ($company) {
            query($pdo, 'INSERT INTO companies (name) VALUES (?)', [trim($data['company_name'])]);
            $companyId = (int) $pdo->lastInsertId();
            query($pdo, "INSERT INTO company_memberships (user_id, company_id, role) VALUES (?, ?, 'owner')", [$id, $companyId]);
        }
        $user = find_user($pdo, $id);
        $pdo->commit();
        return $user;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

/** Lock both buckets until verification completes: no sixth concurrent check. */
function limited_check(PDO $pdo, string $kind, string $identity, callable $verify): array
{
    $now = time();
    $sessionKey = 'attempts_' . $kind;
    $session = $_SESSION[$sessionKey] ?? ['failures' => 0, 'expires' => $now + 900];
    if ($kind !== 'login' && $session['expires'] <= $now) {
        $session = ['failures' => 0, 'expires' => $now + 900];
    }
    if ($session['failures'] >= 5) {
        return ['blocked' => true, 'user' => null];
    }
    $scopes = [
        hash_hmac('sha256', $kind . ':account:' . $identity, app_key()) => 5,
        hash_hmac('sha256', $kind . ':ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'), app_key()) => $kind === 'recovery' ? 10 : 20,
    ];
    ksort($scopes);
    $pdo->beginTransaction();
    try {
        foreach ($scopes as $key => $limit) {
            query($pdo, 'INSERT INTO auth_limits (scope_key, failures, expires_at) VALUES (?, 0, ?)
                ON DUPLICATE KEY UPDATE scope_key = VALUES(scope_key)', [$key, $now + 900]);
            $bucket = query($pdo, 'SELECT failures, expires_at FROM auth_limits WHERE scope_key = ? FOR UPDATE', [$key])->fetch();
            if ((int) $bucket['expires_at'] <= $now) {
                query($pdo, 'UPDATE auth_limits SET failures = 0, expires_at = ? WHERE scope_key = ?', [$now + 900, $key]);
            } elseif ((int) $bucket['failures'] >= $limit) {
                $pdo->commit();
                return ['blocked' => true, 'user' => null];
            }
        }
        $user = $verify();
        if (!$user) {
            foreach ($scopes as $key => $limit) {
                query($pdo, 'UPDATE auth_limits SET failures = failures + 1 WHERE scope_key = ?', [$key]);
            }
            $session['failures']++;
            $_SESSION[$sessionKey] = $session;
        }
        $pdo->commit();
        return ['blocked' => !$user && $session['failures'] >= 5, 'user' => $user];
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function authenticate(PDO $pdo, string $email, string $secret, bool $recovery = false): array
{
    $email = email_normalize($email);
    return limited_check($pdo, $recovery ? 'recovery' : 'login', $email, function () use ($pdo, $email, $secret, $recovery) {
        $validEmail = strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL);
        $user = $validEmail ? query($pdo, 'SELECT * FROM users WHERE email = ?', [$email])->fetch() : false;
        $hash = $user ? ($recovery ? $user['pin_hash'] : $user['password']) : null;
        // Slow verification also for absent/inactive accounts.
        $dummy = defined('PASSWORD_ARGON2ID')
            ? '$argon2id$v=19$m=65536,t=4,p=1$U3dGTlFzY3psR1NUL1k0dg$zP+tI+Qni/GkPUM9rhpoOTfNLNts4+WqjH3HLYcgyJw'
            : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $validFormat = $recovery ? (bool) preg_match('/\A[0-9]{6}\z/', $secret) : (strlen($secret) <= 72 && !str_contains($secret, "\0"));
        $candidate = $recovery ? pin_secret($secret) : ($validFormat ? $secret : 'invalid');
        $valid = password_verify($candidate, $hash ?: $dummy);
        if (!$valid || !$validFormat || !$user || !$user['active'] || !$hash) {
            return null;
        }
        if (!$recovery && password_needs_rehash($hash, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
            query($pdo, 'UPDATE users SET password = ? WHERE id = ? AND password = ?', [hash_secret($secret), $user['id'], $hash]);
        }
        return $user;
    });
}

function verify_current_password(PDO $pdo, array $user, string $password): bool
{
    $result = limited_check($pdo, 'sensitive', (string) $user['id'], function () use ($pdo, $user, $password) {
        $fresh = find_user($pdo, (int) $user['id']);
        return $fresh && $fresh['active'] && (int) $fresh['auth_version'] === (int) $user['auth_version']
            && strlen($password) <= 72 && !str_contains($password, "\0")
            && password_verify($password, $fresh['password']) ? $fresh : null;
    });
    return (bool) $result['user'];
}

function start_recovery(array $user): void
{
    rotate_session();
    unset($_SESSION['auth']);
    $_SESSION['recovery'] = ['id' => (int) $user['id'], 'version' => (int) $user['auth_version'], 'expires' => time() + 300];
}

function recovery_user(PDO $pdo): ?array
{
    $grant = $_SESSION['recovery'] ?? null;
    $user = $grant ? find_user($pdo, $grant['id']) : null;
    if (!$grant || $grant['expires'] <= time() || !$user || !$user['active'] || (int) $user['auth_version'] !== $grant['version']) {
        unset($_SESSION['recovery']);
        return null;
    }
    return $user;
}

function update_secret(PDO $pdo, array $user, string $hash, bool $pin = false): bool
{
    // Column selected by application code, never by a form field.
    $column = $pin ? 'pin_hash' : 'password';
    return query($pdo, "UPDATE users SET $column = ?, auth_version = auth_version + 1 WHERE id = ? AND auth_version = ? AND active = 1",
        [$hash, $user['id'], $user['auth_version']])->rowCount() === 1;
}
