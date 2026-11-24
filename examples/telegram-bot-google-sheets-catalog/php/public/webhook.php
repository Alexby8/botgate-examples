<?php

declare(strict_types=1);
require dirname(__DIR__).'/domain.php';
$secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!is_string($secret) || !hash_equals(envRequired('WEBHOOK_SECRET'), $secret)) {
    http_response_code(403);
    exit;
}
try {
    $raw = file_get_contents('php://input', false, null, 0, 131073);
    if ($raw === false || strlen($raw) > 131072) {
        throw new InvalidArgumentException('Body too large');
    }
    $update = objectValue(json_decode($raw, true, 512, JSON_THROW_ON_ERROR));
    $db = database();
    $response = atomic($db, function () use ($db, $update): string {
        $id = intValue($update['update_id'] ?? null);
        $previous = row($db, 'SELECT response FROM updates WHERE id=?', [$id]);
        if ($previous !== null) {
            return stringValue($previous['response']);
        }
        $response = json_encode(handle($db, $update), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        run($db, 'INSERT INTO updates VALUES(?,?)', [$id, $response]);
        return $response;
    });
    header('Content-Type: application/json');
    echo $response;
} catch (Throwable) {
    // Не печатаем тело update: оно может содержать адрес или текст клиента.
    error_log('Webhook transaction failed');
    http_response_code(503);
}
