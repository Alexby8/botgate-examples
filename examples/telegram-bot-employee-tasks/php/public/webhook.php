<?php

declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/outbox.php';
require dirname(__DIR__).'/domain.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!hash_equals(envRequired('WEBHOOK_SECRET'), stringValue($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''))) {
        http_response_code(403);
        exit;
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 131072) {
        http_response_code(413);
        exit;
    }
    $u = objectValue(json_decode($raw, true, 32, JSON_THROW_ON_ERROR));
    $cb = is_array($u['callback_query'] ?? null) ? objectValue($u['callback_query']) : null;
    $msg = is_array($cb['message'] ?? $u['message'] ?? null) ? objectValue($cb['message'] ?? $u['message']) : [];
    $from = is_array($cb['from'] ?? $msg['from'] ?? null) ? objectValue($cb['from'] ?? $msg['from']) : [];
    $chat = is_array($msg['chat'] ?? null) ? objectValue($msg['chat']) : [];
    $ack = $cb !== null ? ['method' => 'answerCallbackQuery','callback_query_id' => stringValue($cb['id'])] : ['ok' => true];
    if (($chat['type'] ?? '') === 'private' && is_int($from['id'] ?? null) && $from['id'] > 0 && ($chat['id'] ?? null) === $from['id']) {
        $db = database();
        $uid = positive($u['update_id'], PHP_INT_MAX);
        $actor = $from['id'];
        $action = stringValue($cb['data'] ?? $msg['text'] ?? '');
        atomic($db, function () use ($db, $uid, $actor, $action, $cb): void {
            if (run($db, 'INSERT OR IGNORE INTO updates(update_id) VALUES(?)', [$uid])->rowCount() === 0) {
                return;
            }
            // Domain errors become readable replies; internal errors roll back the Update.
            run($db, 'SAVEPOINT action');
            try {
                handle($db, $actor, trim($action), $cb !== null, $uid, time());
                run($db, 'RELEASE action');
            } catch (DomainException $e) {
                run($db, 'ROLLBACK TO action');
                run($db, 'RELEASE action');
                enqueue($db, $actor, 'error:'.$uid, $e->getMessage());
            }
        });
    }
    echo json_encode($ack, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(503);
    echo '{"ok":false}';
}
