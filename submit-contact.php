<?php
declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'form-support.php';

handleFormSubmission('contact.html', function (): array {
    $name = requiredPostValue('name', 60);
    $email = requiredPostValue('email', 254);
    $subject = requiredPostValue('subject', 120);
    $message = requiredPostValue('message', 5000);

    if (!preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} '\\-]{0,59}$/u", $name) ||
        filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
        utf8Length($subject) < 3 ||
        utf8Length($message) < 10) {
        throw new InvalidArgumentException('Contact details are invalid.');
    }

    return [
        'file' => 'contacts.json',
        'data' => [
            'id' => bin2hex(random_bytes(16)),
            'submittedAt' => gmdate('c'),
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
        ],
    ];
});
