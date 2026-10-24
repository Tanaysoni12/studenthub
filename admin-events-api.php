<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'database.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'event-support.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function studentHubEventApiRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function studentHubEventApiReadFields(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (is_string($contentType) && str_starts_with(strtolower($contentType), 'application/json')) {
        $body = file_get_contents('php://input');
        $values = is_string($body) ? json_decode($body, true) : null;
        if (!is_array($values)) {
            throw new InvalidArgumentException('The request body must contain valid JSON.');
        }

        return $values;
    }

    return $_POST;
}

function studentHubEventApiReadId(mixed $value): int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }
    if (is_string($value) && ctype_digit($value)) {
        $eventId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (is_int($eventId)) {
            return $eventId;
        }
    }

    throw new InvalidArgumentException('The event ID is invalid.');
}

function studentHubEventApiFind(int $eventId): ?array
{
    $database = studentHubDatabase();
    $query = $database->prepare(
        'SELECT event_id, title, category, event_date, event_time, location,
                description, badge_class, poster_filename
         FROM events WHERE event_id = ? LIMIT 1'
    );
    $query->bind_param('i', $eventId);
    $query->execute();
    $query->bind_result($id, $title, $category, $date, $time, $location, $description, $badge, $posterFilename);
    $found = $query->fetch();
    $query->close();

    if (!$found) {
        return null;
    }

    return [
        'event_id' => (string) $id,
        'title' => $title,
        'category' => $category,
        'event_date' => $date,
        'event_time' => $time,
        'location' => $location,
        'description' => $description,
        'badge_class' => $badge,
        'poster_filename' => $posterFilename,
        'poster_url' => is_string($posterFilename)
            ? 'php/event-poster.php?id=' . rawurlencode((string) $id) . '&v=' . rawurlencode($posterFilename)
            : null,
    ];
}

try {
    studentHubRequireRole('admin');
} catch (Throwable $error) {
    error_log('StudentHub event API authorization failed: ' . $error->getMessage());
    studentHubEventApiRespond(401, ['error' => 'Sign in with an administrator account to manage events.']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'GET') {
    try {
        $database = studentHubDatabase();
        $query = $database->prepare(
            'SELECT event_id, title, category, event_date, event_time, location,
                    description, badge_class, poster_filename
             FROM events ORDER BY event_date ASC, event_id ASC'
        );
        $query->execute();
        $query->bind_result($id, $title, $category, $date, $time, $location, $description, $badge, $posterFilename);
        $events = [];

        while ($query->fetch()) {
            $events[] = [
                'event_id' => (string) $id,
                'title' => $title,
                'category' => $category,
                'event_date' => $date,
                'event_time' => $time,
                'location' => $location,
                'description' => $description,
                'badge_class' => $badge,
                'poster_filename' => $posterFilename,
                'poster_url' => is_string($posterFilename)
                    ? 'php/event-poster.php?id=' . rawurlencode((string) $id) . '&v=' . rawurlencode($posterFilename)
                    : null,
            ];
        }
        $query->close();
        studentHubEventApiRespond(200, ['events' => $events]);
    } catch (Throwable $error) {
        error_log('StudentHub event API list failed: ' . $error->getMessage());
        studentHubEventApiRespond(500, ['error' => 'Unable to load events.']);
    }
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    studentHubEventApiRespond(405, ['error' => 'This API supports GET and POST requests.']);
}

$newPosterFilename = null;
try {
    $input = studentHubEventApiReadFields();
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
    if (!studentHubVerifyCsrfToken($csrfToken)) {
        studentHubEventApiRespond(403, ['error' => 'Your session token is invalid. Refresh the page and try again.']);
    }

    $action = $input['action'] ?? null;
    if (!is_string($action) || !in_array($action, ['add', 'update', 'delete'], true)) {
        throw new InvalidArgumentException('Choose a valid event operation.');
    }

    $database = studentHubDatabase();
    if ($action === 'delete') {
        $eventId = studentHubEventApiReadId($input['event_id'] ?? null);
        $event = studentHubEventApiFind($eventId);
        if ($event === null) {
            studentHubEventApiRespond(404, ['error' => 'That event could not be found.']);
        }

        $delete = $database->prepare('DELETE FROM events WHERE event_id = ?');
        $delete->bind_param('i', $eventId);
        $delete->execute();
        $deleted = $delete->affected_rows === 1;
        $delete->close();
        if (!$deleted) {
            studentHubEventApiRespond(404, ['error' => 'That event could not be found.']);
        }

        try {
            studentHubDeleteEventPoster($event['poster_filename']);
        } catch (Throwable $error) {
            error_log('StudentHub event API poster cleanup failed: ' . $error->getMessage());
        }
        studentHubEventApiRespond(200, ['message' => 'Event deleted successfully.', 'event_id' => (string) $eventId]);
    }

    $eventId = 0;
    $existingEvent = null;
    if ($action === 'update') {
        $eventId = studentHubEventApiReadId($input['event_id'] ?? null);
        $existingEvent = studentHubEventApiFind($eventId);
        if ($existingEvent === null) {
            studentHubEventApiRespond(404, ['error' => 'That event could not be found.']);
        }
    }

    $event = studentHubValidateEventFields($input);
    $upload = $_FILES['poster'] ?? null;
    if ($upload !== null) {
        if (!is_array($upload) || !isset($upload['error']) || !is_int($upload['error'])) {
            throw new InvalidArgumentException('The poster upload is invalid.');
        }
        if ($upload['error'] !== UPLOAD_ERR_NO_FILE) {
            $newPosterFilename = studentHubStoreEventPoster($upload);
        }
    }

    $removePoster = $action === 'update' &&
        in_array($input['remove_poster'] ?? null, ['on', 'true', true, 1, '1'], true);
    $posterFilename = $newPosterFilename ??
        ($removePoster ? null : ($existingEvent['poster_filename'] ?? null));

    if ($action === 'add') {
        $insert = $database->prepare(
            'INSERT INTO events
              (title, category, event_date, event_time, location, description, badge_class, poster_filename)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->bind_param(
            'ssssssss',
            $event['title'],
            $event['category'],
            $event['event_date'],
            $event['event_time'],
            $event['location'],
            $event['description'],
            $event['badge_class'],
            $posterFilename
        );
        $insert->execute();
        $eventId = (int) $insert->insert_id;
        $insert->close();
    } else {
        $update = $database->prepare(
            'UPDATE events
             SET title = ?, category = ?, event_date = ?, event_time = ?, location = ?,
                 description = ?, badge_class = ?, poster_filename = ?
             WHERE event_id = ?'
        );
        $update->bind_param(
            'ssssssssi',
            $event['title'],
            $event['category'],
            $event['event_date'],
            $event['event_time'],
            $event['location'],
            $event['description'],
            $event['badge_class'],
            $posterFilename,
            $eventId
        );
        $update->execute();
        $update->close();
    }

    $newPosterFilename = null;
    if ($existingEvent !== null && $existingEvent['poster_filename'] !== $posterFilename) {
        try {
            studentHubDeleteEventPoster($existingEvent['poster_filename']);
        } catch (Throwable $error) {
            error_log('StudentHub event API poster cleanup failed: ' . $error->getMessage());
        }
    }

    studentHubEventApiRespond(
        $action === 'add' ? 201 : 200,
        [
            'message' => $action === 'add' ? 'Event added successfully.' : 'Event updated successfully.',
            'event' => studentHubEventApiFind($eventId),
        ]
    );
} catch (InvalidArgumentException $error) {
    if ($newPosterFilename !== null) {
        try {
            studentHubDeleteEventPoster($newPosterFilename);
        } catch (Throwable $cleanupError) {
            error_log('StudentHub event API poster cleanup failed: ' . $cleanupError->getMessage());
        }
    }
    studentHubEventApiRespond(422, ['error' => $error->getMessage()]);
} catch (Throwable $error) {
    if ($newPosterFilename !== null) {
        try {
            studentHubDeleteEventPoster($newPosterFilename);
        } catch (Throwable $cleanupError) {
            error_log('StudentHub event API poster cleanup failed: ' . $cleanupError->getMessage());
        }
    }
    error_log('StudentHub event API operation failed: ' . $error->getMessage());
    studentHubEventApiRespond(500, ['error' => 'The event operation could not be completed.']);
}
