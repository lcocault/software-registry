<?php

declare(strict_types=1);

function getDatabaseConnection(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '5432';
    $dbName = getenv('DB_NAME') ?: 'software_registry';
    $user = getenv('DB_USER') ?: 'postgres';
    $password = getenv('DB_PASSWORD');

    if ($password === false || $password === '') {
        throw new RuntimeException('DB_PASSWORD environment variable must be set.');
    }

    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbName);

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}
