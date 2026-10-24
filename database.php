<?php
declare(strict_types=1);

function studentHubDatabase(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $host = getenv('STUDENTHUB_DB_HOST') ?: '127.0.0.1';
    $port = getenv('STUDENTHUB_DB_PORT') ?: '3306';
    $database = getenv('STUDENTHUB_DB_NAME') ?: 'studenthub';
    $username = getenv('STUDENTHUB_DB_USER');
    $password = getenv('STUDENTHUB_DB_PASSWORD');

    if ($username === false || $username === '') {
        throw new RuntimeException('STUDENTHUB_DB_USER is not configured.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli(
        $host,
        $username,
        $password === false ? '' : $password,
        $database,
        (int) $port
    );
    $connection->set_charset('utf8mb4');

    return $connection;
}
