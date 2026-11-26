<?php
declare(strict_types=1);
require __DIR__.'/common.php';
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$path = setting('BOT_DB_PATH');
if (file_exists($path)) {
    throw new RuntimeException('Database already exists');
}
$schema = file_get_contents(__DIR__.'/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Missing schema.sql');
}
$db = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec($schema);
echo "Database created\n";
