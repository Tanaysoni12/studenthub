<?php
declare(strict_types=1);

const STUDENTHUB_SESSION_TIMEOUT = 1800;
const STUDENTHUB_REMEMBER_SECONDS = 2592000;
const STUDENTHUB_REMEMBER_COOKIE = 'studenthub_remember';

function studentHubStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_name('studenthub_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function studentHubCsrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function studentHubVerifyCsrfToken(mixed $token): bool
{
    return is_string($token) &&
        isset($_SESSION['csrf_token']) &&
        is_string($_SESSION['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $token);
}

function studentHubRedirectToRole(string $role): void
{
    $scriptDirectory = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    if (basename($scriptDirectory) === 'php') {
        $scriptDirectory = dirname($scriptDirectory);
    }
    $rootPath = rtrim(str_replace('\\', '/', $scriptDirectory), '/');
    $rootPath = $rootPath === '' ? '' : $rootPath;
    $destination = match ($role) {
        'admin' => $rootPath . '/admin-dashboard.php',
        'student' => $rootPath . '/dashboard.php',
        default => $rootPath . '/login.php?status=error',
    };

    header('Location: ' . $destination, true, 303);
    exit;
}

function studentHubClearRememberCookie(): void
{
    setcookie(STUDENTHUB_REMEMBER_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[STUDENTHUB_REMEMBER_COOKIE]);
}

function studentHubRememberCookieParts(): ?array
{
    $cookie = $_COOKIE[STUDENTHUB_REMEMBER_COOKIE] ?? null;
    if (!is_string($cookie) || !preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/', $cookie, $parts)) {
        return null;
    }

    return ['selector' => $parts[1], 'validator' => $parts[2]];
}

function studentHubSetRememberCookie(string $selector, string $validator, int $expiresAt): void
{
    setcookie(STUDENTHUB_REMEMBER_COOKIE, $selector . ':' . $validator, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function studentHubRevokeRememberToken(mysqli $database): void
{
    $parts = studentHubRememberCookieParts();

    if ($parts !== null) {
        $statement = $database->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $statement->bind_param('s', $parts['selector']);
        $statement->execute();
        $statement->close();
    }

    studentHubClearRememberCookie();
}

function studentHubIssueRememberToken(mysqli $database, int $studentId): void
{
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $validator);
    $expiresAt = time() + STUDENTHUB_REMEMBER_SECONDS;
    $expiresAtSql = gmdate('Y-m-d H:i:s', $expiresAt);

    $statement = $database->prepare(
        'INSERT INTO remember_tokens (selector, student_id, token_hash, expires_at)
         VALUES (?, ?, ?, ?)'
    );
    $statement->bind_param('siss', $selector, $studentId, $tokenHash, $expiresAtSql);
    $statement->execute();
    $statement->close();
    studentHubSetRememberCookie($selector, $validator, $expiresAt);
}

function studentHubLoginUser(array $user): void
{
    if (!in_array($user['role'] ?? null, ['student', 'admin'], true)) {
        throw new RuntimeException('Account has an unsupported role.');
    }

    session_regenerate_id(true);
    $_SESSION = [
        'student_id' => (int) $user['student_id'],
        'username' => (string) $user['username'],
        'full_name' => (string) $user['full_name'],
        'role' => (string) $user['role'],
        'last_activity' => time(),
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
}

function studentHubTryRememberLogin(): bool
{
    $parts = studentHubRememberCookieParts();
    if ($parts === null) {
        if (isset($_COOKIE[STUDENTHUB_REMEMBER_COOKIE])) {
            studentHubClearRememberCookie();
        }
        return false;
    }

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'database.php';
    $database = studentHubDatabase();
    $lookup = $database->prepare(
        'SELECT student_id, token_hash
         FROM remember_tokens
         WHERE selector = ? AND expires_at > UTC_TIMESTAMP()
         LIMIT 1'
    );
    $lookup->bind_param('s', $parts['selector']);
    $lookup->execute();
    $lookup->bind_result($studentId, $storedHash);
    $tokenFound = $lookup->fetch();
    $lookup->close();

    if (!$tokenFound || !hash_equals($storedHash, hash('sha256', $parts['validator']))) {
        studentHubClearRememberCookie();
        return false;
    }

    $studentId = (int) $studentId;
    $userQuery = $database->prepare(
        'SELECT student_id, username, full_name, role
         FROM students
         WHERE student_id = ?
         LIMIT 1'
    );
    $userQuery->bind_param('i', $studentId);
    $userQuery->execute();
    $userQuery->bind_result($id, $username, $fullName, $role);
    $userFound = $userQuery->fetch();
    $userQuery->close();

    if (!$userFound || !in_array($role, ['student', 'admin'], true)) {
        $delete = $database->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $delete->bind_param('s', $parts['selector']);
        $delete->execute();
        $delete->close();
        studentHubClearRememberCookie();
        return false;
    }

    $database->begin_transaction();
    try {
        $delete = $database->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $delete->bind_param('s', $parts['selector']);
        $delete->execute();
        $delete->close();
        studentHubIssueRememberToken($database, $studentId);
        $database->commit();
    } catch (Throwable $error) {
        $database->rollback();
        throw $error;
    }

    studentHubLoginUser([
        'student_id' => $id,
        'username' => $username,
        'full_name' => $fullName,
        'role' => $role,
    ]);

    return true;
}

function studentHubRequireAuthenticatedUser(): array
{
    studentHubStartSession();
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    $sessionExpired = false;

    if (isset($_SESSION['student_id'], $_SESSION['role'], $_SESSION['last_activity']) &&
        time() - (int) $_SESSION['last_activity'] > STUDENTHUB_SESSION_TIMEOUT) {
        $sessionExpired = true;
        $_SESSION = [];
        session_regenerate_id(true);
    }

    if (!isset($_SESSION['student_id'], $_SESSION['role'])) {
        try {
            if (!studentHubTryRememberLogin()) {
                if (str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')) {
                    http_response_code(401);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => 'Sign in to continue.']);
                    exit;
                }
                header('Location: login.php?status=' . ($sessionExpired ? 'timeout' : 'required'), true, 303);
                exit;
            }
        } catch (Throwable $error) {
            error_log('StudentHub session restoration failed: ' . $error->getMessage());
            if (str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Unable to verify the current session.']);
                exit;
            }
            header('Location: login.php?status=error', true, 303);
            exit;
        }
    }

    $_SESSION['last_activity'] = time();
    return [
        'student_id' => (int) $_SESSION['student_id'],
        'username' => (string) ($_SESSION['username'] ?? ''),
        'full_name' => (string) ($_SESSION['full_name'] ?? ''),
        'role' => (string) $_SESSION['role'],
    ];
}

function studentHubRequireRole(string $requiredRole): array
{
    $user = studentHubRequireAuthenticatedUser();
    if ($user['role'] !== $requiredRole) {
        if (str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'You do not have permission to perform this action.']);
            exit;
        }
        studentHubRedirectToRole($user['role']);
    }

    return $user;
}

function studentHubDestroySession(): void
{
    studentHubStartSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();
    studentHubClearRememberCookie();
}
