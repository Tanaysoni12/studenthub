<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'database.php';

studentHubStartSession();

if (isset($_SESSION['student_id'], $_SESSION['role'])) {
    studentHubRedirectToRole((string) $_SESSION['role']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    header('Location: ../login.php?status=method', true, 303);
    exit;
}

if (!studentHubVerifyCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: ../login.php?status=invalid', true, 303);
    exit;
}

$identifier = $_POST['identifier'] ?? null;
$password = $_POST['password'] ?? null;

if (!is_string($identifier) || !is_string($password)) {
    header('Location: ../login.php?status=invalid', true, 303);
    exit;
}

$identifier = trim($identifier);
if ($identifier === '' ||
    strlen($identifier) > 254 ||
    (filter_var($identifier, FILTER_VALIDATE_EMAIL) === false &&
        !preg_match('/^[A-Za-z][A-Za-z0-9_.-]{2,29}$/', $identifier)) ||
    $password === '' ||
    strlen($password) > 4096) {
    header('Location: ../login.php?status=invalid', true, 303);
    exit;
}

try {
    $database = studentHubDatabase();
    $statement = $database->prepare(
        'SELECT student_id, username, full_name, email, password_hash, role
         FROM students
         WHERE email = ? OR username = ?
         LIMIT 1'
    );
    $statement->bind_param('ss', $identifier, $identifier);
    $statement->execute();
    $statement->bind_result($studentId, $username, $fullName, $email, $passwordHash, $role);
    $userFound = $statement->fetch();
    $statement->close();

    if (!$userFound || !password_verify($password, $passwordHash)) {
        header('Location: ../login.php?status=invalid', true, 303);
        exit;
    }

    if (!in_array($role, ['student', 'admin'], true)) {
        throw new RuntimeException('Account has an unsupported role.');
    }

    if (password_needs_rehash($passwordHash, PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        if ($newHash === false) {
            throw new RuntimeException('Unable to update password hash.');
        }
        $rehash = $database->prepare('UPDATE students SET password_hash = ? WHERE student_id = ?');
        $studentIdForUpdate = (int) $studentId;
        $rehash->bind_param('si', $newHash, $studentIdForUpdate);
        $rehash->execute();
        $rehash->close();
    }

    studentHubLoginUser([
        'student_id' => $studentId,
        'username' => $username,
        'full_name' => $fullName,
        'role' => $role,
    ]);

    studentHubRevokeRememberToken($database);
    if (isset($_POST['remember']) && $_POST['remember'] === 'on') {
        studentHubIssueRememberToken($database, (int) $studentId);
    } else {
        studentHubClearRememberCookie();
    }

    studentHubRedirectToRole($role);
} catch (Throwable $error) {
    error_log('StudentHub login failed: ' . $error->getMessage());
    studentHubDestroySession();
    header('Location: ../login.php?status=error', true, 303);
    exit;
}
