<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$path = getenv('BOT_DB_PATH');
if (!$path || file_exists($path)) throw new RuntimeException('Нужен путь к новой БД');
$db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA foreign_keys = ON');
$schema = file_get_contents(__DIR__.'/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Не найден schema.sql');
}
$db->exec($schema);
echo "База создана\n";
