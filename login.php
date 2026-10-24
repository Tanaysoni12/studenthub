<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'auth.php';
studentHubStartSession();

if (isset($_SESSION['student_id'], $_SESSION['role'])) {
    studentHubRedirectToRole((string) $_SESSION['role']);
}

$csrfToken = studentHubCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>StudentHub | Login</title>
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
        <a href="dashboard.php">Dashboard</a>
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
        <a class="signin active" href="login.php">Sign In</a>
      
        <button class="theme-toggle" type="button" aria-pressed="false"><span>☀ <span>Light mode</span></span></button>
      </nav>
    </div>
  </header>

  <main>
    <section class="page-header">
      <div class="container page-header-inner">
        <div>
          <span class="page-tag">access portal</span>
          <h1>Student Login</h1>
          <p>Access your student account and campus information securely.</p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <form class="form-card" action="php/login.php" method="post">
          <h2>Login to your account</h2>
          <div class="form-grid">
            <div class="form-group full">
              <label for="identifier">Email or Username</label>
              <input id="identifier" name="identifier" type="text" placeholder="Email address or username"
                autocomplete="username" maxlength="254" required />
            </div>
            <div class="form-group full">
              <label for="password">Password</label>
              <input id="password" name="password" type="password" placeholder="Enter password"
                autocomplete="current-password" maxlength="4096" required />
            </div>
          </div>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
          <div class="form-group choice-group" style="margin-top: 16px;">
            <label class="choice-label" for="remember">
              <input id="remember" name="remember" type="checkbox" />
              <span>Remember me on this device for 30 days</span>
            </label>
          </div>
          <p class="form-message" id="login-message" role="status" aria-live="polite" hidden></p>
          <div class="btn-row">
            <button class="btn primary" type="submit">Login</button>
            <a class="btn secondary" href="register.html">Create Account</a>
          </div>
        </form>
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
  <script>
    (function () {
      const form = document.querySelector('form[action="php/login.php"]');
      const identifier = document.getElementById("identifier");
      const usernamePattern = /^[A-Za-z][A-Za-z0-9_.-]{2,29}$/;
      const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
      const identifierMessage = "Enter a valid email address or a username with 3-30 permitted characters.";

      identifier.addEventListener("input", function () {
        const value = identifier.value.trim();
        identifier.setCustomValidity(
          value && !emailPattern.test(value) && !usernamePattern.test(value)
            ? identifierMessage
            : ""
        );
      });

      form.addEventListener("submit", function (event) {
        const value = identifier.value.trim();
        identifier.setCustomValidity(
          value && !emailPattern.test(value) && !usernamePattern.test(value)
            ? identifierMessage
            : ""
        );
        if (!form.checkValidity()) {
          event.preventDefault();
          form.reportValidity();
        }
      });

      const messages = {
        invalid: "The email/username or password is incorrect, or the form expired.",
        required: "Please sign in to access the dashboard.",
        timeout: "Your session expired after 30 minutes of inactivity. Please sign in again.",
        error: "We could not sign you in right now. Please try again later.",
        method: "Submit the login form using the sign-in button.",
        "logged-out": "You have been securely signed out."
      };
      const status = new URLSearchParams(window.location.search).get("status");
      const message = Object.prototype.hasOwnProperty.call(messages, status) ? messages[status] : "";

      if (message) {
        const messageElement = document.getElementById("login-message");
        messageElement.textContent = message;
        messageElement.dataset.state = status === "logged-out" ? "success" : "error";
        messageElement.hidden = false;
        window.history.replaceState({}, document.title, window.location.pathname + window.location.hash);
      }
    })();
  </script>
</body>
</html>
