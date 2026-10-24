<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'auth.php';
$currentUser = studentHubRequireRole('student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>StudentHub | Dashboard</title>
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
        <a href="index.html">Home</a>
        <a class="active" href="dashboard.php">Dashboard</a>
        <a href="timetable.html">Timetable</a>
        <a href="attendance.html">Attendance</a>
        <a href="assignment.html">Assignments</a>
        <a href="notice.html">Notices</a>
        <a href="result.html">Results</a>
        <a href="events.html">Events</a>
        <a href="profile.html">Profile</a>
        <a href="contact.html">Contact</a>
        <a href="feedback.html">Feedback</a>
        <a href="about.html">About</a>
        <form class="nav-logout" action="php/logout.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(studentHubCsrfToken(), ENT_QUOTES, 'UTF-8') ?>" />
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
          <span class="page-tag">student dashboard</span>
          <h1>Student Dashboard</h1>
          <p>Welcome back, <?= htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8') ?>. Keep track of your learning progress and daily activities.</p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="dashboard-grid">
          <div class="dashboard-card">
            <span class="badge success">Current</span>
            <div class="value">6</div>
            <div>Active Courses</div>
          </div>
          <div class="dashboard-card">
            <span class="badge info">Average</span>
            <div class="value">3.8</div>
            <div>GPA</div>
          </div>
          <div class="dashboard-card">
            <span class="badge warning">Progress</span>
            <div class="value">91%</div>
            <div>Attendance</div>
          </div>
          <div class="dashboard-card">
            <span class="badge danger">Pending</span>
            <div class="value">04</div>
            <div>Assignments</div>
          </div>
        </div>

        <div class="card-grid" style="margin-top: 24px;">
          <article class="card">
            <h3>Today's tasks</h3>
            <ul class="list">
              <li>Submit Web Development project</li>
              <li>Review database assignment brief</li>
              <li>Attend group discussion</li>
            </ul>
          </article>

          <article class="card">
            <h3>Upcoming events</h3>
            <ul class="list">
              <li>Career Fair - 14 Apr</li>
              <li>IT Workshop - 17 Apr</li>
              <li>Research Seminar - 19 Apr</li>
            </ul>
          </article>

          <article class="card">
            <h3>Announcements</h3>
            <ul class="list">
              <li>Midterm results published</li>
              <li>Library hours extended</li>
              <li>Scholarship deadline reminder</li>
            </ul>
          </article>
        </div>
      </div>
    </section>
  </main>

  <footer>
    <div class="container footer-wrap">
      <p>Â© 2026 StudentHub Portal</p>
      <a href="terms.html">Terms & Conditions</a>
    </div>
  </footer>
  <script src="js/theme.js"></script>
</body>
</html>
