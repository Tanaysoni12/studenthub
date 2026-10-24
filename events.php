<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('X-Content-Type-Options: nosniff');

try {
    $database = studentHubDatabase();
    $statement = $database->prepare(
        'SELECT event_id, title, category, event_date, event_time, location,
                description, badge_class, poster_filename
         FROM events
         ORDER BY event_date ASC, event_id ASC'
    );
    $statement->execute();
    $statement->bind_result($id, $title, $category, $date, $time, $location, $description, $badge, $posterFilename);
    $events = [];

    while ($statement->fetch()) {
        $event = [
            'id' => (string) $id,
            'title' => $title,
            'category' => $category,
            'date' => $date,
            'time' => $time,
            'location' => $location,
            'description' => $description,
            'badge' => $badge,
        ];
        if (is_string($posterFilename) && preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/', $posterFilename)) {
            $event['posterUrl'] = 'php/event-poster.php?id=' .
                rawurlencode((string) $id) . '&v=' . rawurlencode($posterFilename);
        }
        $events[] = $event;
    }

    $statement->close();
    echo json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    error_log('StudentHub event list failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load events.'], JSON_UNESCAPED_SLASHES);
}
