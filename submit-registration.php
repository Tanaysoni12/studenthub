<?php
declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'form-support.php';

requirePostRequest('register.html');

try {
    $name = requiredPostValue('fullName', 60);
    $username = requiredPostValue('username', 30);
    $email = requiredPostValue('email', 254);
    $mobile = requiredPostValue('mobile', 16);
    $course = requiredPostValue('course', 40);
    $year = requiredPostValue('year', 1);
    $password = requiredPostValue('password', 64, false);
    $confirmPassword = requiredPostValue('confirmPassword', 64, false);
    $gender = requiredPostValue('gender', 30);

    $courses = ['computer-science', 'business-studies', 'engineering', 'arts-humanities', 'science'];
    $genders = ['female', 'male', 'other', 'prefer-not-to-say'];

    if (!preg_match("/^[A-Za-z][A-Za-z '\\-]{0,59}$/", $name) ||
        !preg_match('/^[A-Za-z][A-Za-z0-9_.-]{2,29}$/', $username) ||
        filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
        !preg_match('/^\+?[0-9]{10,15}$/', $mobile) ||
        !in_array($course, $courses, true) ||
        !in_array($year, ['1', '2', '3', '4'], true) ||
        !in_array($gender, $genders, true) ||
        !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,64}$/', $password) ||
        !hash_equals($password, $confirmPassword) ||
        !isset($_POST['terms']) || $_POST['terms'] !== 'on') {
        throw new InvalidArgumentException('Registration details are invalid.');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('Unable to hash registration password.');
    }

    require __DIR__ . DIRECTORY_SEPARATOR . 'database.php';
    $database = studentHubDatabase();
    $duplicateCheck = $database->prepare(
        'SELECT email, username
         FROM students
         WHERE email = ? OR username = ?
         LIMIT 1'
    );
    $duplicateCheck->bind_param('ss', $email, $username);
    $duplicateCheck->execute();
    $duplicateCheck->bind_result($existingEmail, $existingUsername);
    $hasDuplicate = $duplicateCheck->fetch();
    $duplicateCheck->close();

    if ($hasDuplicate) {
        $duplicateEmail = strcasecmp($existingEmail, $email) === 0;
        $duplicateUsername = strcasecmp($existingUsername, $username) === 0;

        if ($duplicateEmail && $duplicateUsername) {
            redirectToForm('register.html', 'duplicate');
        }
        if ($duplicateEmail) {
            redirectToForm('register.html', 'duplicate-email');
        }
        redirectToForm('register.html', 'duplicate-username');
    }

    $statement = $database->prepare(
        'INSERT INTO students
          (full_name, username, email, mobile, course, study_year, gender, password_hash, terms_accepted_at)
         VALUES
          (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $studyYear = (int) $year;
    $statement->bind_param(
        'sssssiss',
        $name,
        $username,
        $email,
        $mobile,
        $course,
        $studyYear,
        $gender,
        $passwordHash
    );
    $statement->execute();
    $statement->close();

    redirectToForm('register.html', 'success');
} catch (InvalidArgumentException $error) {
    redirectToForm('register.html', 'invalid');
} catch (mysqli_sql_exception $error) {
    if ($error->getCode() === 1062) {
        redirectToForm('register.html', 'duplicate');
    }

    error_log('StudentHub registration database failure: ' . $error->getMessage());
    redirectToForm('register.html', 'error');
} catch (Throwable $error) {
    error_log('StudentHub registration failed: ' . $error->getMessage());
    redirectToForm('register.html', 'error');
}
