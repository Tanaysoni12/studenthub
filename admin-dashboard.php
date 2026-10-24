<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'database.php';
$currentUser = studentHubRequireRole('admin');

$navigationItems = $currentUser['role'] === 'admin'
    ? [
        ['label' => 'Home', 'href' => 'index.html'],
        ['label' => 'Admin Dashboard', 'href' => 'admin-dashboard.php'],
        ['label' => 'Manage Students', 'href' => 'admin-students.php'],
        ['label' => 'Manage Events', 'href' => 'admin-events.php'],
    ]
    : [
        ['label' => 'Home', 'href' => 'index.html'],
        ['label' => 'Dashboard', 'href' => 'dashboard.php'],
        ['label' => 'Events', 'href' => 'events.html'],
    ];

$dashboardStats = null;
$latestRegistrations = [];
$dashboardError = false;

try {
    $database = studentHubDatabase();

    $studentStatsQuery = $database->prepare(
        "SELECT COUNT(*) AS user_count,
                COALESCE(SUM(role = 'student'), 0) AS student_count,
                COALESCE(SUM(role = 'admin'), 0) AS admin_count
         FROM students"
    );
    $studentStatsQuery->execute();
    $studentStatsQuery->bind_result($userCount, $studentCount, $adminCount);
    if (!$studentStatsQuery->fetch()) {
        throw new RuntimeException('Unable to load user statistics.');
    }
    $studentStatsQuery->close();

    $eventCountQuery = $database->prepare('SELECT COUNT(*) FROM events');
    $eventCountQuery->execute();
    $eventCountQuery->bind_result($eventCount);
    if (!$eventCountQuery->fetch()) {
        throw new RuntimeException('Unable to load the event count.');
    }
    $eventCountQuery->close();

    $registrationCountQuery = $database->prepare('SELECT COUNT(*) FROM registrations');
    $registrationCountQuery->execute();
    $registrationCountQuery->bind_result($registrationCount);
    if (!$registrationCountQuery->fetch()) {
        throw new RuntimeException('Unable to load registration statistics.');
    }
    $registrationCountQuery->close();

    $dashboardStats = [
        'users' => (int) $userCount,
        'students' => (int) $studentCount,
        'admins' => (int) $adminCount,
        'events' => (int) $eventCount,
        'registrations' => (int) $registrationCount,
    ];

    $latestQuery = $database->prepare(
        'SELECT r.registration_id, r.status, r.registered_at,
                s.full_name, s.email, e.title
         FROM registrations AS r
         INNER JOIN students AS s ON s.student_id = r.student_id
         INNER JOIN events AS e ON e.event_id = r.event_id
         ORDER BY r.registered_at DESC, r.registration_id DESC
         LIMIT 8'
    );
    $latestQuery->execute();
    $latestQuery->bind_result($registrationId, $registrationStatus, $registeredAt, $studentName, $studentEmail, $eventTitle);
    while ($latestQuery->fetch()) {
        $latestRegistrations[] = [
            'id' => (int) $registrationId,
            'status' => $registrationStatus,
            'registered_at' => $registeredAt,
            'student_name' => $studentName,
            'student_email' => $studentEmail,
            'event_title' => $eventTitle,
        ];
    }
    $latestQuery->close();
} catch (Throwable $error) {
    error_log('StudentHub admin dashboard statistics failed: ' . $error->getMessage());
    $dashboardError = true;
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrfToken = studentHubCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>StudentHub | Admin Dashboard</title>
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
  <header class="topbar">
    <div class="container navbar">
      <a class="brand" href="index.html">
        <span class="brand-mark">S</span>
        <span>StudentHub</span>
      </a>
      <nav class="nav-links" aria-label="Main navigation">
        <?php foreach ($navigationItems as $item): ?>
          <a href="<?= $escape($item['href']) ?>"<?= $item['href'] === 'admin-dashboard.php' ? ' class="active" aria-current="page"' : '' ?>><?= $escape($item['label']) ?></a>
        <?php endforeach; ?>
        <form class="nav-logout" action="php/logout.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>" />
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
          <span class="page-tag">administrator</span>
          <h1>Admin Dashboard</h1>
          <p>Welcome, <?= $escape($currentUser['full_name']) ?>. Your administrator account is signed in.</p>
        </div>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <?php if ($dashboardError): ?>
          <p class="form-message" data-state="error" role="alert">Dashboard statistics could not be loaded. Please try again later.</p>
        <?php else: ?>
        <div class="dashboard-grid admin-stat-grid">
          <article class="dashboard-card">
            <span class="badge info">Users</span>
            <div class="value"><?= number_format($dashboardStats['users']) ?></div>
            <div>Total accounts, including <?= number_format($dashboardStats['admins']) ?> admin<?= $dashboardStats['admins'] === 1 ? '' : 's' ?></div>
          </article>
          <article class="dashboard-card">
            <span class="badge success">Students</span>
            <div class="value"><?= number_format($dashboardStats['students']) ?></div>
            <div>Registered students</div>
          </article>
          <article class="dashboard-card">
            <span class="badge warning">Events</span>
            <div class="value"><?= number_format($dashboardStats['events']) ?></div>
            <div>Published events</div>
          </article>
          <article class="dashboard-card">
            <span class="badge danger">Registrations</span>
            <div class="value"><?= number_format($dashboardStats['registrations']) ?></div>
            <div>Total event registrations</div>
          </article>
        </div>

        <section class="panel admin-dashboard-panel" aria-labelledby="admin-actions-heading">
          <h2 id="admin-actions-heading">Administration</h2>
          <p>Manage student accounts and campus events.</p>
          <div class="btn-row">
            <a class="btn primary" href="admin-students.php">Manage Students</a>
            <a class="btn secondary" href="admin-events.php">Manage Events</a>
          </div>
        </section>

        <section class="management-list admin-registrations" aria-labelledby="latest-registrations-heading">
          <div class="section-heading">
            <h2 id="latest-registrations-heading">Latest Registrations</h2>
            <p>Most recent <?= count($latestRegistrations) ?> event registration<?= count($latestRegistrations) === 1 ? '' : 's' ?>.</p>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th scope="col">Student</th>
                  <th scope="col">Event</th>
                  <th scope="col">Registered</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($latestRegistrations as $registration): ?>
                  <tr>
                    <td><?= $escape($registration['student_name']) ?><br /><span class="meta"><?= $escape($registration['student_email']) ?></span></td>
                    <td><?= $escape($registration['event_title']) ?></td>
                    <td><?= $escape(date('M j, Y g:i A', strtotime($registration['registered_at']))) ?></td>
                    <td><span class="badge <?= $registration['status'] === 'registered' ? 'success' : 'warning' ?>"><?= $escape(ucfirst($registration['status'])) ?></span></td>
                  </tr>
                <?php endforeach; ?>
                <?php if ($latestRegistrations === []): ?>
                  <tr><td colspan="4">There are no event registrations yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>
        <?php endif; ?>
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
