<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'database.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'event-support.php';

$eventIdRaw = $_GET['id'] ?? null;
if (!is_string($eventIdRaw) || !ctype_digit($eventIdRaw) || (int) $eventIdRaw < 1) {
    http_response_code(404);
    exit;
}

try {
    $eventId = (int) $eventIdRaw;
    $database = studentHubDatabase();
    $statement = $database->prepare('SELECT poster_filename FROM events WHERE event_id = ? LIMIT 1');
    $statement->bind_param('i', $eventId);
    $statement->execute();
    $statement->bind_result($filename);
    $found = $statement->fetch();
    $statement->close();

    if (!$found || !is_string($filename) ||
        !preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/', $filename)) {
        http_response_code(404);
        exit;
    }

    $path = studentHubEventPosterDirectory() . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($path);
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
} catch (Throwable $error) {
    error_log('StudentHub event poster request failed: ' . $error->getMessage());
    http_response_code(500);
}
