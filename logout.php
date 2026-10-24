<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
studentHubStartSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !studentHubVerifyCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: ../login.php?status=invalid', true, 303);
    exit;
}

$parts = studentHubRememberCookieParts();
if ($parts !== null) {
    try {
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'database.php';
        studentHubRevokeRememberToken(studentHubDatabase());
    } catch (Throwable $error) {
        error_log('StudentHub remember token revocation failed: ' . $error->getMessage());
    }
}

studentHubDestroySession();
header('Location: ../login.php?status=logged-out', true, 303);
exit;
