<?php
declare(strict_types=1);
require dirname(__DIR__).'/database.php';
require dirname(__DIR__).'/store-form.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
$secret = getenv('WEBHOOK_SECRET');
if ($secret === false || $secret === '') {
    http_response_code(503);
    exit;
}
$received = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!is_string($received) || !hash_equals($secret, $received)) {
    http_response_code(403);
    exit;
}
try {
    $raw = file_get_contents('php://input', false, null, 0, 1048577);
    if ($raw === false || strlen($raw) > 1048576) {
        http_response_code(413);
        exit;
    }
    $update = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    http_response_code(400);
    exit;
}
if (!is_array($update) || !is_int($update['update_id'] ?? null)) {
    http_response_code(400);
    exit;
}
try {
    applyFormUpdate(database(), 'consultation', $update, time());
    http_response_code(200);
} catch (Throwable) {
    error_log('Form update could not be committed');
    http_response_code(503);
}
