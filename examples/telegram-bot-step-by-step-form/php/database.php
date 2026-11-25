<?php
declare(strict_types=1);

function database(): PDO
{
    $db = new PDO('sqlite:'.__DIR__.'/var/form.sqlite', options: [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA busy_timeout = 5000');
    return $db;
}
