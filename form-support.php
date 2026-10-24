<?php
declare(strict_types=1);

function redirectToForm(string $form, string $status): void
{
    header('Location: ../' . $form . '?status=' . rawurlencode($status), true, 303);
    exit;
}

function requirePostRequest(string $form): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        redirectToForm($form, 'method');
    }
}

function utf8Length(string $value): int
{
    $length = preg_match_all('/./us', $value, $matches);

    if ($length === false) {
        throw new InvalidArgumentException('A submitted field is invalid.');
    }

    return $length;
}

function requiredPostValue(string $key, int $maxLength, bool $stripHtml = true): string
{
    if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
        throw new InvalidArgumentException('A required field is missing.');
    }

    $value = $stripHtml ? trim(strip_tags($_POST[$key])) : $_POST[$key];

    if (preg_match('//u', $value) !== 1 ||
        preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1 ||
        utf8Length($value) > $maxLength) {
        throw new InvalidArgumentException('A submitted field is invalid.');
    }

    return $value;
}

function appendJsonRecord(string $fileName, array $record): void
{
    $configuredDirectory = getenv('STUDENTHUB_DATA_DIR');
    $directory = $configuredDirectory !== false && $configuredDirectory !== ''
        ? $configuredDirectory
        : dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . basename(dirname(__DIR__)) . '-private-data';

    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create private data directory.');
    }

    $path = $directory . DIRECTORY_SEPARATOR . $fileName;
    $file = fopen($path, 'c+');

    if ($file === false) {
        throw new RuntimeException('Unable to open data file.');
    }

    try {
        if (!flock($file, LOCK_EX)) {
            throw new RuntimeException('Unable to lock data file.');
        }

        rewind($file);
        $contents = stream_get_contents($file);

        if ($contents === false) {
            throw new RuntimeException('Unable to read data file.');
        }

        $records = $contents === '' ? [] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($records) ||
            ($records !== [] && array_keys($records) !== range(0, count($records) - 1))) {
            throw new RuntimeException('Stored data is not a JSON record list.');
        }

        $records[] = $record;
        $json = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

        if (!ftruncate($file, 0) || rewind($file) === false) {
            throw new RuntimeException('Unable to prepare data file for writing.');
        }

        $written = 0;
        $length = strlen($json);
        while ($written < $length) {
            $bytes = fwrite($file, substr($json, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('Unable to save data file.');
            }
            $written += $bytes;
        }

        if (!fflush($file)) {
            throw new RuntimeException('Unable to flush data file.');
        }

        flock($file, LOCK_UN);
    } finally {
        fclose($file);
    }
}

function handleFormSubmission(string $form, callable $process): void
{
    requirePostRequest($form);

    try {
        $record = $process();
        appendJsonRecord($record['file'], $record['data']);
        redirectToForm($form, 'success');
    } catch (InvalidArgumentException $error) {
        redirectToForm($form, 'invalid');
    } catch (Throwable $error) {
        error_log('StudentHub form submission failed: ' . $error->getMessage());
        redirectToForm($form, 'error');
    }
}
