<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'database.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'event-support.php';

$currentUser = studentHubRequireRole('admin');
$badges = [
    'info' => 'Info',
    'success' => 'Success',
    'warning' => 'Warning',
    'danger' => 'Important',
];
$statusMessages = [
    'added' => ['Event added successfully.', 'success'],
    'updated' => ['Event updated successfully.', 'success'],
    'deleted' => ['Event deleted successfully.', 'success'],
    'invalid' => ['Check the event details and poster requirements, then try again.', 'error'],
    'not-found' => ['That event could not be found.', 'error'],
    'error' => ['The event operation could not be completed. Please try again.', 'error'],
];
$status = $_GET['status'] ?? '';
$notice = is_string($status) && isset($statusMessages[$status]) ? $statusMessages[$status] : null;
$newPosterFilename = null;

function studentHubEventEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function studentHubEventRedirect(string $status): never
{
    header('Location: admin-events.php?status=' . rawurlencode($status), true, 303);
    exit;
}

function studentHubEventDeletePosterQuietly(?string $filename): void
{
    try {
        studentHubDeleteEventPoster($filename);
    } catch (Throwable $error) {
        error_log('StudentHub event poster cleanup failed: ' . $error->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!studentHubVerifyCsrfToken($_POST['csrf_token'] ?? null)) {
        studentHubEventRedirect('invalid');
    }

    try {
        $database = studentHubDatabase();
        $action = $_POST['action'] ?? null;

        if ($action === 'add' || $action === 'update') {
            $eventId = 0;
            $existingPoster = null;

            if ($action === 'update') {
                $eventIdRaw = $_POST['event_id'] ?? null;
                if (!is_string($eventIdRaw) || !ctype_digit($eventIdRaw) || (int) $eventIdRaw < 1) {
                    throw new InvalidArgumentException('Event ID is invalid.');
                }
                $eventId = (int) $eventIdRaw;

                $existingQuery = $database->prepare('SELECT poster_filename FROM events WHERE event_id = ? LIMIT 1');
                $existingQuery->bind_param('i', $eventId);
                $existingQuery->execute();
                $existingQuery->bind_result($existingPosterResult);
                $eventExists = $existingQuery->fetch();
                if ($eventExists) {
                    $existingPoster = is_string($existingPosterResult) ? $existingPosterResult : null;
                }
                $existingQuery->close();
                if (!$eventExists) {
                    studentHubEventRedirect('not-found');
                }
            }

            $eventFields = studentHubValidateEventFields($_POST);
            $title = $eventFields['title'];
            $category = $eventFields['category'];
            $eventDate = $eventFields['event_date'];
            $eventTime = $eventFields['event_time'];
            $location = $eventFields['location'];
            $description = $eventFields['description'];
            $badge = $eventFields['badge_class'];

            $removePoster = $action === 'update' &&
                isset($_POST['remove_poster']) && $_POST['remove_poster'] === 'on';
            $upload = $_FILES['poster'] ?? null;
            if ($upload !== null) {
                if (!is_array($upload) || !isset($upload['error']) || !is_int($upload['error'])) {
                    throw new InvalidArgumentException('The poster upload is invalid.');
                }
                if ($upload['error'] !== UPLOAD_ERR_NO_FILE) {
                    $newPosterFilename = studentHubStoreEventPoster($upload);
                }
            }

            $posterFilename = $newPosterFilename ?? ($removePoster ? null : $existingPoster);

            if ($action === 'add') {
                $insert = $database->prepare(
                    'INSERT INTO events
                      (title, category, event_date, event_time, location, description, badge_class, poster_filename)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->bind_param(
                    'ssssssss',
                    $title,
                    $category,
                    $eventDate,
                    $eventTime,
                    $location,
                    $description,
                    $badge,
                    $posterFilename
                );
                $insert->execute();
                $insert->close();
                $newPosterFilename = null;
                studentHubEventRedirect('added');
            }

            $update = $database->prepare(
                'UPDATE events
                 SET title = ?, category = ?, event_date = ?, event_time = ?, location = ?,
                     description = ?, badge_class = ?, poster_filename = ?
                 WHERE event_id = ?'
            );
            $update->bind_param(
                'ssssssssi',
                $title,
                $category,
                $eventDate,
                $eventTime,
                $location,
                $description,
                $badge,
                $posterFilename,
                $eventId
            );
            $update->execute();
            $update->close();
            $newPosterFilename = null;

            if ($existingPoster !== $posterFilename) {
                studentHubEventDeletePosterQuietly($existingPoster);
            }

            studentHubEventRedirect('updated');
        }

        if ($action === 'delete') {
            $eventIdRaw = $_POST['event_id'] ?? null;
            if (!is_string($eventIdRaw) || !ctype_digit($eventIdRaw) || (int) $eventIdRaw < 1) {
                throw new InvalidArgumentException('Event ID is invalid.');
            }
            $eventId = (int) $eventIdRaw;

            $find = $database->prepare('SELECT poster_filename FROM events WHERE event_id = ? LIMIT 1');
            $find->bind_param('i', $eventId);
            $find->execute();
            $find->bind_result($posterToDeleteResult);
            $found = $find->fetch();
            $posterToDelete = $found && is_string($posterToDeleteResult) ? $posterToDeleteResult : null;
            $find->close();

            if (!$found) {
                studentHubEventRedirect('not-found');
            }

            $delete = $database->prepare('DELETE FROM events WHERE event_id = ?');
            $delete->bind_param('i', $eventId);
            $delete->execute();
            $deleted = $delete->affected_rows === 1;
            $delete->close();

            if (!$deleted) {
                studentHubEventRedirect('not-found');
            }

            studentHubEventDeletePosterQuietly($posterToDelete);
            studentHubEventRedirect('deleted');
        }

        studentHubEventRedirect('invalid');
    } catch (InvalidArgumentException $error) {
        studentHubEventDeletePosterQuietly($newPosterFilename);
        studentHubEventRedirect('invalid');
    } catch (Throwable $error) {
        studentHubEventDeletePosterQuietly($newPosterFilename);
        error_log('StudentHub event management failed: ' . $error->getMessage());
        studentHubEventRedirect('error');
    }
}

$events = [];
$editingEvent = null;
$editIdRaw = isset($_GET['edit']) && is_string($_GET['edit']) ? $_GET['edit'] : '';
$editId = ctype_digit($editIdRaw) ? (int) $editIdRaw : 0;

try {
    $database = studentHubDatabase();

    if ($editId > 0) {
        $editQuery = $database->prepare(
            'SELECT event_id, title, category, event_date, event_time, location, description, badge_class, poster_filename
             FROM events WHERE event_id = ? LIMIT 1'
        );
        $editQuery->bind_param('i', $editId);
        $editQuery->execute();
        $editQuery->bind_result($eventId, $title, $category, $eventDate, $eventTime, $location, $description, $badge, $posterFilename);
        if ($editQuery->fetch()) {
            $editingEvent = [
                'event_id' => $eventId,
                'title' => $title,
                'category' => $category,
                'event_date' => $eventDate,
                'event_time' => $eventTime,
                'location' => $location,
                'description' => $description,
                'badge_class' => $badge,
                'poster_filename' => $posterFilename,
            ];
        } else {
            $notice = $statusMessages['not-found'];
        }
        $editQuery->close();
    }

    $list = $database->prepare(
        'SELECT event_id, title, category, event_date, event_time, location, badge_class, poster_filename
         FROM events ORDER BY event_date ASC, event_id ASC'
    );
    $list->execute();
    $list->bind_result($listId, $listTitle, $listCategory, $listDate, $listTime, $listLocation, $listBadge, $listPoster);
    while ($list->fetch()) {
        $events[] = [
            'event_id' => $listId,
            'title' => $listTitle,
            'category' => $listCategory,
            'event_date' => $listDate,
            'event_time' => $listTime,
            'location' => $listLocation,
            'badge_class' => $listBadge,
            'poster_filename' => $listPoster,
        ];
    }
    $list->close();
} catch (Throwable $error) {
    error_log('StudentHub event list failed: ' . $error->getMessage());
    $notice = $statusMessages['error'];
}

$csrfToken = studentHubCsrfToken();
$formValues = $editingEvent ?? [
    'title' => '',
    'category' => '',
    'event_date' => '',
    'event_time' => '',
    'location' => '',
    'description' => '',
    'badge_class' => 'info',
    'poster_filename' => null,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>StudentHub | Manage Events</title>
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
  <header class="topbar">
    <div class="container navbar">
      <a class="brand" href="index.html"><span class="brand-mark">S</span><span>StudentHub</span></a>
      <nav class="nav-links" aria-label="Main navigation">
        <a href="index.html">Home</a>
        <a href="admin-dashboard.php">Admin Dashboard</a>
        <a href="admin-students.php">Manage Students</a>
        <a class="active" href="admin-events.php">Manage Events</a>
        <form class="nav-logout" action="php/logout.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= studentHubEventEscape($csrfToken) ?>" />
          <button class="signin" type="submit">Sign Out</button>
        </form>
        <button class="theme-toggle" type="button" aria-pressed="false"><span>☀ <span>Light mode</span></span></button>
      </nav>
    </div>
  </header>

  <main>
    <section class="page-header">
      <div class="container page-header-inner">
        <div>
          <span class="page-tag">campus life</span>
          <h1>Manage Events</h1>
          <p>Add and update event details, publish posters, or remove outdated events.</p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <?php if ($notice !== null): ?>
          <p class="form-message management-message" data-state="<?= studentHubEventEscape($notice[1]) ?>" role="status" aria-live="polite"><?= studentHubEventEscape($notice[0]) ?></p>
        <?php endif; ?>

        <section class="form-card management-form" aria-labelledby="event-form-heading">
          <h2 id="event-form-heading"><?= $editingEvent !== null ? 'Edit Event' : 'Add Event' ?></h2>
          <p>Posters: JPEG, PNG, or WebP only; maximum size 5 MB.</p>
          <form action="admin-events.php" method="post" enctype="multipart/form-data" data-event-form data-api-url="php/admin-events-api.php">
            <input type="hidden" name="csrf_token" value="<?= studentHubEventEscape($csrfToken) ?>" />
            <input type="hidden" name="action" value="<?= $editingEvent !== null ? 'update' : 'add' ?>" data-event-action />
            <input type="hidden" name="event_id" value="<?= $editingEvent !== null ? (int) $editingEvent['event_id'] : '' ?>" data-event-id />
            <div class="form-grid">
              <div class="form-group">
                <label for="event-title">Event Title</label>
                <input id="event-title" name="title" type="text" value="<?= studentHubEventEscape($formValues['title']) ?>" minlength="3" maxlength="160" required />
              </div>
              <div class="form-group">
                <label for="event-category">Category</label>
                <input id="event-category" name="category" type="text" value="<?= studentHubEventEscape($formValues['category']) ?>" minlength="2" maxlength="60" pattern="[\p{L}\p{M}0-9][\p{L}\p{M}0-9 &amp;()'.,_/\-]{1,59}" required />
              </div>
              <div class="form-group">
                <label for="event-date">Date</label>
                <input id="event-date" name="event_date" type="date" value="<?= studentHubEventEscape($formValues['event_date']) ?>" required />
              </div>
              <div class="form-group">
                <label for="event-time">Time</label>
                <input id="event-time" name="event_time" type="text" value="<?= studentHubEventEscape($formValues['event_time']) ?>" maxlength="60" placeholder="9:00 AM - 1:00 PM" required />
              </div>
              <div class="form-group">
                <label for="event-location">Location</label>
                <input id="event-location" name="location" type="text" value="<?= studentHubEventEscape($formValues['location']) ?>" minlength="2" maxlength="160" required />
              </div>
              <div class="form-group">
                <label for="badge-class">Badge Style</label>
                <select id="badge-class" name="badge_class" required>
                  <?php foreach ($badges as $value => $label): ?>
                    <option value="<?= studentHubEventEscape($value) ?>" <?= $formValues['badge_class'] === $value ? 'selected' : '' ?>><?= studentHubEventEscape($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group full">
                <label for="event-description">Description</label>
                <textarea id="event-description" name="description" minlength="10" maxlength="5000" required><?= studentHubEventEscape($formValues['description']) ?></textarea>
              </div>
              <div class="form-group full">
                <label for="poster">Event Poster (optional)</label>
                <input id="poster" name="poster" type="file" accept="image/jpeg,image/png,image/webp" data-poster-input />
                <p class="form-hint" data-poster-message role="status" aria-live="polite">Allowed: JPEG, PNG, or WebP up to 5 MB.</p>
              </div>
              <div class="form-group full">
                <div data-event-poster-preview<?= $editingEvent === null || !is_string($formValues['poster_filename']) ? ' hidden' : '' ?>>
                <?php if ($editingEvent !== null && is_string($formValues['poster_filename'])): ?>
                  <span>Current poster</span>
                  <img class="poster-preview" src="php/event-poster.php?id=<?= (int) $editingEvent['event_id'] ?>&amp;v=<?= rawurlencode($formValues['poster_filename']) ?>" alt="Current poster for <?= studentHubEventEscape($formValues['title']) ?>" />
                <?php endif; ?>
                </div>
                <label class="choice-label" for="remove-poster" data-event-remove-poster<?= $editingEvent === null || !is_string($formValues['poster_filename']) ? ' hidden' : '' ?>>
                  <input id="remove-poster" name="remove_poster" type="checkbox" />
                  <span>Remove current poster</span>
                </label>
              </div>
            </div>
            <p class="form-message" data-event-status role="status" aria-live="polite" hidden></p>
            <div class="btn-row">
              <button class="btn primary" type="submit" data-event-submit><?= $editingEvent !== null ? 'Save Event' : 'Add Event' ?></button>
              <button class="btn secondary" type="button" data-event-cancel<?= $editingEvent === null ? ' hidden' : '' ?>>Cancel</button>
            </div>
          </form>
        </section>

        <section class="management-list" aria-labelledby="event-list-heading">
          <div class="section-heading">
            <h2 id="event-list-heading">Events</h2>
            <p data-event-count><?= count($events) ?> event<?= count($events) === 1 ? '' : 's' ?>.</p>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th scope="col">Event</th>
                  <th scope="col">Category</th>
                  <th scope="col">Date and Time</th>
                  <th scope="col">Location</th>
                  <th scope="col">Poster</th>
                  <th scope="col">Actions</th>
                </tr>
              </thead>
              <tbody data-event-list data-api-url="php/admin-events-api.php" data-csrf-token="<?= studentHubEventEscape($csrfToken) ?>">
                <?php foreach ($events as $event): ?>
                  <tr>
                    <td><?= studentHubEventEscape($event['title']) ?></td>
                    <td><span class="badge <?= studentHubEventEscape($event['badge_class']) ?>"><?= studentHubEventEscape($event['category']) ?></span></td>
                    <td><?= studentHubEventEscape(date('M j, Y', strtotime($event['event_date']))) ?><br /><span class="meta"><?= studentHubEventEscape($event['event_time']) ?></span></td>
                    <td><?= studentHubEventEscape($event['location']) ?></td>
                    <td><?= is_string($event['poster_filename']) ? 'Uploaded' : 'None' ?></td>
                    <td>
                      <div class="student-actions">
                        <button class="btn secondary" type="button" data-event-edit="<?= (int) $event['event_id'] ?>">Edit</button>
                        <form action="admin-events.php" method="post" data-event-delete data-event-id="<?= (int) $event['event_id'] ?>">
                          <input type="hidden" name="csrf_token" value="<?= studentHubEventEscape($csrfToken) ?>" />
                          <input type="hidden" name="action" value="delete" />
                          <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>" />
                          <button class="btn danger-button" type="submit">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if ($events === []): ?><tr><td colspan="6">No events have been added.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </section>
  </main>

  <footer>
    <div class="container footer-wrap">
      <p>Â© 2026 StudentHub Portal</p>
      <a href="terms.html">Terms &amp; Conditions</a>
    </div>
  </footer>
  <script src="js/theme.js"></script>
</body>
</html>
