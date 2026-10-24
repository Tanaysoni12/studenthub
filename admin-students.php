<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'database.php';

$currentUser = studentHubRequireRole('admin');
$database = null;
$courses = [
    'computer-science' => 'Computer Science',
    'business-studies' => 'Business Studies',
    'engineering' => 'Engineering',
    'arts-humanities' => 'Arts & Humanities',
    'science' => 'Science',
];
$genders = [
    'female' => 'Female',
    'male' => 'Male',
    'other' => 'Other',
    'prefer-not-to-say' => 'Prefer not to say',
];
$statusMessages = [
    'added' => ['Student added successfully.', 'success'],
    'updated' => ['Student record updated successfully.', 'success'],
    'deleted' => ['Student deleted successfully.', 'success'],
    'duplicate' => ['A student with that username, email, or mobile number already exists.', 'error'],
    'invalid' => ['Please check the submitted values and try again.', 'error'],
    'not-found' => ['That student record could not be found.', 'error'],
    'error' => ['The operation could not be completed. Please try again.', 'error'],
];
$status = $_GET['status'] ?? '';
$notice = is_string($status) && isset($statusMessages[$status]) ? $statusMessages[$status] : null;

function studentManagementRedirect(string $status): never
{
    header('Location: admin-students.php?status=' . rawurlencode($status), true, 303);
    exit;
}

function studentManagementPostValue(string $key, int $maximum): string
{
    if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
        throw new InvalidArgumentException('A required field is missing.');
    }

    $value = trim($_POST[$key]);
    if (preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
        throw new InvalidArgumentException('A field contains invalid characters.');
    }

    $length = preg_match_all('/./us', $value);
    if ($length === false || $length > $maximum) {
        throw new InvalidArgumentException('A field is too long.');
    }

    return $value;
}

function studentManagementEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!studentHubVerifyCsrfToken($_POST['csrf_token'] ?? null)) {
        studentManagementRedirect('invalid');
    }

    $action = $_POST['action'] ?? null;
    try {
        $database = studentHubDatabase();

        if ($action === 'add' || $action === 'update') {
            $studentId = 0;
            if ($action === 'update') {
                $studentIdRaw = $_POST['student_id'] ?? null;
                if (!is_string($studentIdRaw) || !ctype_digit($studentIdRaw) || (int) $studentIdRaw < 1) {
                    throw new InvalidArgumentException('Student ID is invalid.');
                }
                $studentId = (int) $studentIdRaw;
            }

            $fullName = studentManagementPostValue('full_name', 60);
            $username = studentManagementPostValue('username', 30);
            $email = studentManagementPostValue('email', 254);
            $mobile = studentManagementPostValue('mobile', 16);
            $course = studentManagementPostValue('course', 40);
            $yearRaw = studentManagementPostValue('study_year', 1);
            $gender = studentManagementPostValue('gender', 30);
            $year = filter_var($yearRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);

            if (!preg_match("/^[A-Za-z][A-Za-z '\\-]{0,59}$/", $fullName) ||
                !preg_match('/^[A-Za-z][A-Za-z0-9_.-]{2,29}$/', $username) ||
                filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
                !preg_match('/^\+?[0-9]{10,15}$/', $mobile) ||
                !isset($courses[$course]) ||
                $year === false ||
                !isset($genders[$gender])) {
                throw new InvalidArgumentException('Student details are invalid.');
            }

            $duplicate = $database->prepare(
                'SELECT student_id
                 FROM students
                 WHERE role = \'student\'
                   AND (username = ? OR email = ? OR mobile = ?)
                   AND student_id <> ?
                 LIMIT 1'
            );
            $duplicate->bind_param('sssi', $username, $email, $mobile, $studentId);
            $duplicate->execute();
            $duplicate->store_result();
            $hasDuplicate = $duplicate->num_rows > 0;
            $duplicate->close();

            if ($hasDuplicate) {
                studentManagementRedirect('duplicate');
            }

            if ($action === 'add') {
                $password = studentManagementPostValue('password', 64);
                if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,64}$/', $password)) {
                    throw new InvalidArgumentException('Use a strong password with uppercase, lowercase, number, and special character.');
                }

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                if ($passwordHash === false) {
                    throw new RuntimeException('Unable to hash student password.');
                }

                $insert = $database->prepare(
                    'INSERT INTO students
                      (full_name, username, email, mobile, course, study_year, gender, password_hash, terms_accepted_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
                );
                $insert->bind_param(
                    'sssssiss',
                    $fullName,
                    $username,
                    $email,
                    $mobile,
                    $course,
                    $year,
                    $gender,
                    $passwordHash
                );
                $insert->execute();
                $insert->close();
                studentManagementRedirect('added');
            }

            $update = $database->prepare(
                'UPDATE students
                 SET full_name = ?, username = ?, email = ?, mobile = ?, course = ?, study_year = ?, gender = ?
                 WHERE student_id = ? AND role = \'student\''
            );
            $update->bind_param(
                'sssssisi',
                $fullName,
                $username,
                $email,
                $mobile,
                $course,
                $year,
                $gender,
                $studentId
            );
            $update->execute();
            $updatedRows = $update->affected_rows;
            $update->close();

            if ($updatedRows === 0) {
                $exists = $database->prepare('SELECT student_id FROM students WHERE student_id = ? AND role = \'student\'');
                $exists->bind_param('i', $studentId);
                $exists->execute();
                $exists->store_result();
                $studentExists = $exists->num_rows > 0;
                $exists->close();
                studentManagementRedirect($studentExists ? 'updated' : 'not-found');
            }

            studentManagementRedirect('updated');
        }

        if ($action === 'delete') {
            $studentIdRaw = $_POST['student_id'] ?? null;
            if (!is_string($studentIdRaw) || !ctype_digit($studentIdRaw) || (int) $studentIdRaw < 1) {
                throw new InvalidArgumentException('Student ID is invalid.');
            }
            $studentId = (int) $studentIdRaw;
            $delete = $database->prepare('DELETE FROM students WHERE student_id = ? AND role = \'student\'');
            $delete->bind_param('i', $studentId);
            $delete->execute();
            $deletedRows = $delete->affected_rows;
            $delete->close();
            studentManagementRedirect($deletedRows === 1 ? 'deleted' : 'not-found');
        }

        studentManagementRedirect('invalid');
    } catch (InvalidArgumentException $error) {
        studentManagementRedirect('invalid');
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() === 1062) {
            studentManagementRedirect('duplicate');
        }
        error_log('StudentHub student management database failure: ' . $error->getMessage());
        studentManagementRedirect('error');
    } catch (Throwable $error) {
        error_log('StudentHub student management failure: ' . $error->getMessage());
        studentManagementRedirect('error');
    }
}

$search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
$selectedCourse = isset($_GET['course']) && is_string($_GET['course']) ? $_GET['course'] : '';
$selectedYearRaw = isset($_GET['year']) && is_string($_GET['year']) ? $_GET['year'] : '';
$selectedYear = $selectedYearRaw !== '' && ctype_digit($selectedYearRaw) &&
    (int) $selectedYearRaw >= 1 && (int) $selectedYearRaw <= 4 ? (int) $selectedYearRaw : 0;
if (!isset($courses[$selectedCourse])) {
    $selectedCourse = '';
}
$searchCharacters = [];
if (preg_match_all('/./us', $search, $searchCharacters) !== false) {
    $search = implode('', array_slice($searchCharacters[0], 0, 100));
} else {
    $search = '';
}
$editIdRaw = isset($_GET['edit']) && is_string($_GET['edit']) ? $_GET['edit'] : '';
$editId = ctype_digit($editIdRaw) ? (int) $editIdRaw : 0;
$editingStudent = null;

try {
    $database = studentHubDatabase();

    if ($editId > 0) {
        $editQuery = $database->prepare(
            'SELECT student_id, full_name, username, email, mobile, course, study_year, gender
             FROM students
             WHERE student_id = ? AND role = \'student\'
             LIMIT 1'
        );
        $editQuery->bind_param('i', $editId);
        $editQuery->execute();
        $editQuery->bind_result($editStudentId, $editName, $editUsername, $editEmail, $editMobile, $editCourse, $editYear, $editGender);
        if ($editQuery->fetch()) {
            $editingStudent = [
                'student_id' => $editStudentId,
                'full_name' => $editName,
                'username' => $editUsername,
                'email' => $editEmail,
                'mobile' => $editMobile,
                'course' => $editCourse,
                'study_year' => $editYear,
                'gender' => $editGender,
            ];
        } else {
            $notice = $statusMessages['not-found'];
        }
        $editQuery->close();
    }

    $listQuery = $database->prepare(
        'SELECT student_id, full_name, username, email, mobile, course, study_year, gender, created_at
         FROM students
         WHERE role = \'student\'
           AND (? = \'\' OR CONCAT_WS(\' \', full_name, username, email, mobile) LIKE CONCAT(\'%\', ?, \'%\'))
           AND (? = \'\' OR course = ?)
           AND (? = 0 OR study_year = ?)
         ORDER BY created_at DESC, student_id DESC'
    );
    $listQuery->bind_param('ssssii', $search, $search, $selectedCourse, $selectedCourse, $selectedYear, $selectedYear);
    $listQuery->execute();
    $listQuery->bind_result($studentId, $fullName, $username, $email, $mobile, $listCourse, $listYear, $gender, $createdAt);
    $students = [];
    while ($listQuery->fetch()) {
        $students[] = [
            'student_id' => $studentId,
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'mobile' => $mobile,
            'course' => $listCourse,
            'study_year' => $listYear,
            'gender' => $gender,
            'created_at' => $createdAt,
        ];
    }
    $listQuery->close();
} catch (Throwable $error) {
    error_log('StudentHub student list failed: ' . $error->getMessage());
    $students = [];
    $notice = $statusMessages['error'];
}

$csrfToken = studentHubCsrfToken();
$formValues = $editingStudent ?? [
    'full_name' => '',
    'username' => '',
    'email' => '',
    'mobile' => '',
    'course' => '',
    'study_year' => '',
    'gender' => '',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>StudentHub | Manage Students</title>
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
  <header class="topbar">
    <div class="container navbar">
      <a class="brand" href="index.html"><span class="brand-mark">S</span><span>StudentHub</span></a>
      <nav class="nav-links" aria-label="Main navigation">
        <a href="index.html">Home</a>
        <a href="admin-dashboard.php">Admin Dashboard</a>
        <a class="active" href="admin-students.php">Manage Students</a>
        <form class="nav-logout" action="php/logout.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= studentManagementEscape($csrfToken) ?>" />
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
          <span class="page-tag">student records</span>
          <h1>Manage Students</h1>
          <p>Welcome, <?= studentManagementEscape($currentUser['full_name']) ?>. Add, update, search, filter, or remove student accounts.</p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <?php if ($notice !== null): ?>
          <p class="form-message management-message" data-state="<?= studentManagementEscape($notice[1]) ?>" role="status" aria-live="polite"><?= studentManagementEscape($notice[0]) ?></p>
        <?php endif; ?>

        <section class="form-card management-form" aria-labelledby="student-form-heading">
          <h2 id="student-form-heading"><?= $editingStudent !== null ? 'Edit Student' : 'Add Student' ?></h2>
          <?php if ($editingStudent !== null): ?>
            <p>Editing student #<?= studentManagementEscape((string) $editingStudent['student_id']) ?>. Passwords are not displayed or changed here.</p>
          <?php else: ?>
            <p>New accounts are created with the student role. Use a strong initial password and share it securely.</p>
          <?php endif; ?>
          <form action="admin-students.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= studentManagementEscape($csrfToken) ?>" />
            <input type="hidden" name="action" value="<?= $editingStudent !== null ? 'update' : 'add' ?>" />
            <?php if ($editingStudent !== null): ?>
              <input type="hidden" name="student_id" value="<?= studentManagementEscape((string) $editingStudent['student_id']) ?>" />
            <?php endif; ?>
            <div class="form-grid">
              <div class="form-group">
                <label for="full_name">Full Name</label>
                <input id="full_name" name="full_name" type="text" value="<?= studentManagementEscape((string) $formValues['full_name']) ?>" maxlength="60" pattern="[A-Za-z][A-Za-z '\-]{0,59}" required />
              </div>
              <div class="form-group">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" value="<?= studentManagementEscape((string) $formValues['username']) ?>" minlength="3" maxlength="30" pattern="[A-Za-z][A-Za-z0-9_.\-]{2,29}" required />
              </div>
              <div class="form-group">
                <label for="student-email">Email</label>
                <input id="student-email" name="email" type="email" value="<?= studentManagementEscape((string) $formValues['email']) ?>" maxlength="254" required />
              </div>
              <div class="form-group">
                <label for="mobile">Mobile Number</label>
                <input id="mobile" name="mobile" type="tel" value="<?= studentManagementEscape((string) $formValues['mobile']) ?>" pattern="\+?[0-9]{10,15}" maxlength="16" required />
              </div>
              <div class="form-group">
                <label for="course">Course</label>
                <select id="course" name="course" required>
                  <option value="" disabled <?= $formValues['course'] === '' ? 'selected' : '' ?>>Select a course</option>
                  <?php foreach ($courses as $value => $label): ?>
                    <option value="<?= studentManagementEscape($value) ?>" <?= $formValues['course'] === $value ? 'selected' : '' ?>><?= studentManagementEscape($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="study_year">Year</label>
                <select id="study_year" name="study_year" required>
                  <option value="" disabled <?= $formValues['study_year'] === '' ? 'selected' : '' ?>>Select a year</option>
                  <?php for ($yearOption = 1; $yearOption <= 4; $yearOption++): ?>
                    <option value="<?= $yearOption ?>" <?= (string) $formValues['study_year'] === (string) $yearOption ? 'selected' : '' ?>><?= $yearOption ?><?= $yearOption === 1 ? 'st' : ($yearOption === 2 ? 'nd' : ($yearOption === 3 ? 'rd' : 'th')) ?> Year</option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                  <option value="" disabled <?= $formValues['gender'] === '' ? 'selected' : '' ?>>Select gender</option>
                  <?php foreach ($genders as $value => $label): ?>
                    <option value="<?= studentManagementEscape($value) ?>" <?= $formValues['gender'] === $value ? 'selected' : '' ?>><?= studentManagementEscape($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php if ($editingStudent === null): ?>
                <div class="form-group">
                  <label for="initial-password">Initial Password</label>
                  <input id="initial-password" name="password" type="password" minlength="8" maxlength="64" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,64}" autocomplete="new-password" required />
                </div>
              <?php endif; ?>
            </div>
            <div class="btn-row">
              <button class="btn primary" type="submit"><?= $editingStudent !== null ? 'Save Changes' : 'Add Student' ?></button>
              <?php if ($editingStudent !== null): ?>
                <a class="btn secondary" href="admin-students.php">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </section>

        <section class="management-list" aria-labelledby="student-list-heading">
          <div class="section-heading">
            <h2 id="student-list-heading">Student Records</h2>
            <p><?= count($students) ?> student<?= count($students) === 1 ? '' : 's' ?> found.</p>
          </div>
          <form class="list-tools student-filters" action="admin-students.php" method="get">
            <div class="form-group">
              <label for="student-search">Search</label>
              <input id="student-search" name="search" type="search" value="<?= studentManagementEscape($search) ?>" placeholder="Name, username, email, or mobile" />
            </div>
            <div class="form-group">
              <label for="filter-course">Course</label>
              <select id="filter-course" name="course">
                <option value="">All courses</option>
                <?php foreach ($courses as $value => $label): ?>
                  <option value="<?= studentManagementEscape($value) ?>" <?= $selectedCourse === $value ? 'selected' : '' ?>><?= studentManagementEscape($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="filter-year">Year</label>
              <select id="filter-year" name="year">
                <option value="">All years</option>
                <?php for ($yearOption = 1; $yearOption <= 4; $yearOption++): ?>
                  <option value="<?= $yearOption ?>" <?= $selectedYear === $yearOption ? 'selected' : '' ?>><?= $yearOption ?><?= $yearOption === 1 ? 'st' : ($yearOption === 2 ? 'nd' : ($yearOption === 3 ? 'rd' : 'th')) ?> Year</option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="student-filter-actions">
              <button class="btn primary" type="submit">Apply Filters</button>
              <a class="btn secondary" href="admin-students.php">Clear</a>
            </div>
          </form>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th scope="col">Student</th>
                  <th scope="col">Contact</th>
                  <th scope="col">Course / Year</th>
                  <th scope="col">Gender</th>
                  <th scope="col">Added</th>
                  <th scope="col">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($students as $student): ?>
                  <tr>
                    <td>
                      <strong><?= studentManagementEscape($student['full_name']) ?></strong><br />
                      <span class="meta">@<?= studentManagementEscape($student['username']) ?></span>
                    </td>
                    <td><?= studentManagementEscape($student['email']) ?><br /><span class="meta"><?= studentManagementEscape($student['mobile']) ?></span></td>
                    <td><?= studentManagementEscape($courses[$student['course']] ?? $student['course']) ?><br /><span class="meta">Year <?= studentManagementEscape((string) $student['study_year']) ?></span></td>
                    <td><?= studentManagementEscape($genders[$student['gender']] ?? $student['gender']) ?></td>
                    <td><?= studentManagementEscape(date('M j, Y', strtotime($student['created_at']))) ?></td>
                    <td>
                      <div class="student-actions">
                        <a class="btn secondary" href="admin-students.php?edit=<?= (int) $student['student_id'] ?>">Edit</a>
                        <form action="admin-students.php" method="post" onsubmit="return confirm('Delete this student? Their event registrations and remember-me tokens will also be removed.');">
                          <input type="hidden" name="csrf_token" value="<?= studentManagementEscape($csrfToken) ?>" />
                          <input type="hidden" name="action" value="delete" />
                          <input type="hidden" name="student_id" value="<?= (int) $student['student_id'] ?>" />
                          <button class="btn danger-button" type="submit">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if ($students === []): ?>
                  <tr><td colspan="6">No student records match these filters.</td></tr>
                <?php endif; ?>
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
