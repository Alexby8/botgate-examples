<?php

declare(strict_types=1);
require dirname(__DIR__).'/common.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
$secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!is_string($secret) || !hash_equals(envRequired('WEBHOOK_SECRET'), $secret)) {
    http_response_code(403);
    exit;
}
$raw = file_get_contents('php://input', false, null, 0, 65537);
if (!is_string($raw) || strlen($raw) > 65536) {
    http_response_code(413);
    exit;
}
try {
    $update = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($update) || !is_int($update['update_id'] ?? null)) {
        http_response_code(400);
        exit;
    }
    $stmt = database()->prepare('INSERT OR IGNORE INTO inbox(update_id,payload,created_at) VALUES(?,?,?)');
    $stmt->execute([$update['update_id'], $raw, time()]);
    http_response_code(200);
} catch (Throwable) {
    http_response_code(503);
}
