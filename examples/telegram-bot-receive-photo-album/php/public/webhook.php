<?php

declare(strict_types=1);

require dirname(__DIR__).'/common.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(['ok' => false], 405);
}
$secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!is_string($secret) || !hash_equals(setting('WEBHOOK_SECRET'), $secret)) {
    reply(['ok' => false], 403);
}
try {
    $update = jsonBody();
    $id = $update['update_id'] ?? null;
    if (!is_int($id) || $id < 0) {
        reply(['ok' => false], 400);
    }
    sql(database(), 'INSERT OR IGNORE INTO inbox(update_id,payload) VALUES(?,?)',
        [$id, json_encode($update, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
} catch (InvalidArgumentException|JsonException) {
    reply(['ok' => false], 400);
} catch (Throwable) {
    reply(['ok' => false], 503);
}
$callback = objectOrEmpty($update['callback_query'] ?? null);
if (is_string($callback['id'] ?? null)) {
    reply(['method' => 'answerCallbackQuery', 'callback_query_id' => $callback['id']]);
}
reply(['ok' => true]);
