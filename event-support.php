<?php
declare(strict_types=1);

const STUDENTHUB_MAX_POSTER_BYTES = 5242880;

function studentHubEventTextLength(string $value): int
{
    $length = preg_match_all('/./us', $value);
    if ($length === false) {
        throw new InvalidArgumentException('A field contains invalid text.');
    }

    return $length;
}

function studentHubEventDateIsValid(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    return $date !== false &&
        ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) &&
        $date->format('Y-m-d') === $value;
}

function studentHubValidateEventFields(array $values): array
{
    $limits = [
        'title' => 160,
        'category' => 60,
        'event_date' => 10,
        'event_time' => 60,
        'location' => 160,
        'description' => 5000,
        'badge_class' => 10,
    ];
    $fields = [];

    foreach ($limits as $name => $maximum) {
        if (!isset($values[$name]) || !is_string($values[$name])) {
            throw new InvalidArgumentException('A required event field is missing.');
        }

        $value = trim($values[$name]);
        if (preg_match('//u', $value) !== 1 ||
            preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1 ||
            studentHubEventTextLength($value) > $maximum) {
            throw new InvalidArgumentException('An event field contains invalid text.');
        }
        $fields[$name] = $value;
    }

    $badges = ['info', 'success', 'warning', 'danger'];
    if (studentHubEventTextLength($fields['title']) < 3 ||
        !preg_match("/^[\\p{L}\\p{M}0-9][\\p{L}\\p{M}0-9 &()'.,_\\/-]{1,59}$/u", $fields['category']) ||
        !studentHubEventDateIsValid($fields['event_date']) ||
        $fields['event_time'] === '' ||
        studentHubEventTextLength($fields['location']) < 2 ||
        studentHubEventTextLength($fields['description']) < 10 ||
        !in_array($fields['badge_class'], $badges, true)) {
        throw new InvalidArgumentException('Event details are invalid.');
    }

    return $fields;
}

function studentHubEventPosterDirectory(): string
{
    $configuredDirectory = getenv('STUDENTHUB_DATA_DIR');
    $dataDirectory = $configuredDirectory !== false && $configuredDirectory !== ''
        ? $configuredDirectory
        : dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . basename(dirname(__DIR__)) . '-private-data';

    return $dataDirectory . DIRECTORY_SEPARATOR . 'event-posters';
}

function studentHubStoreEventPoster(array $upload): string
{
    if (!isset($upload['error'], $upload['tmp_name'], $upload['size']) ||
        !is_int($upload['error']) || !is_string($upload['tmp_name']) || !is_int($upload['size'])) {
        throw new InvalidArgumentException('The poster upload is invalid.');
    }

    if ($upload['error'] !== UPLOAD_ERR_OK) {
        if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Choose a poster image to upload.');
        }
        if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('Poster images must be 5 MB or smaller.');
        }
        throw new InvalidArgumentException('The poster upload failed. Please try again.');
    }

    if ($upload['size'] < 1 || $upload['size'] > STUDENTHUB_MAX_POSTER_BYTES ||
        !is_uploaded_file($upload['tmp_name'])) {
        throw new InvalidArgumentException('Poster images must be between 1 byte and 5 MB.');
    }

    $mimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($upload['tmp_name']);
    $imageInfo = getimagesize($upload['tmp_name']);

    if (!is_string($mimeType) || !isset($mimeTypes[$mimeType]) ||
        $imageInfo === false || !isset($imageInfo['mime']) || $imageInfo['mime'] !== $mimeType) {
        throw new InvalidArgumentException('Upload a valid JPEG, PNG, or WebP image.');
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];
    if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000 ||
        $width * $height > 40000000) {
        throw new InvalidArgumentException('Poster image dimensions are too large.');
    }

    $directory = studentHubEventPosterDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create poster storage directory.');
    }

    $filename = bin2hex(random_bytes(24)) . '.' . $mimeTypes[$mimeType];
    if (!move_uploaded_file($upload['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Unable to store poster image.');
    }

    return $filename;
}

function studentHubDeleteEventPoster(?string $filename): void
{
    if ($filename === null || !preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/', $filename)) {
        return;
    }

    $path = studentHubEventPosterDirectory() . DIRECTORY_SEPARATOR . $filename;
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Unable to remove an event poster.');
    }
}
