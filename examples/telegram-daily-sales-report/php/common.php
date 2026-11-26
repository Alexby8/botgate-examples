<?php

declare(strict_types=1);
require_once __DIR__.'/values.php';

function envRequired(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException('Missing environment: '.$name);
    }
    return $value;
}

function database(): PDO
{
    $db = new PDO('sqlite:'.envRequired('APP_DB'), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout=5000');
    return $db;
}
