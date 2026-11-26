<?php

declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/auth.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try {
    $userId = telegramUser(stringValue($_SERVER['HTTP_X_TELEGRAM_INIT_DATA'] ?? ''), envRequired('BOT_TOKEN'), time());
} catch (Throwable) {
    http_response_code(401);
    exit;
}
$id = $_GET['id'] ?? '';
if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) {
    http_response_code(404);
    exit;
}
$q = database()->prepare('SELECT a.* FROM attachments a JOIN requests r ON r.id=a.request_id WHERE a.id=? AND r.user_id=?');
$q->execute([$id,$userId]);
$row = $q->fetch(PDO::FETCH_ASSOC);
if (!is_array($row)) {
    http_response_code(404);
    exit;
}
$name = stringValue($row['storage_name']);
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|pdf)$/D', $name)) {
    http_response_code(500);
    exit;
}
$path = rtrim(envRequired('UPLOAD_DIR'), '/\\').DIRECTORY_SEPARATOR.$name;
$file = fopen($path, 'rb');
if ($file === false) {
    http_response_code(503);
    exit;
}
header('Content-Type: '.stringValue($row['mime']));
header('Content-Disposition: attachment; filename="'.$name.'"');
header('Content-Length: '.intValue($row['bytes']));
fpassthru($file);
fclose($file);
