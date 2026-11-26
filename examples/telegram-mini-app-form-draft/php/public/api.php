<?php

declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/drafts.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
/**
 * @param array<string,mixed> $body
 */
function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}
try {
    $userId = telegramUser(stringValue($_SERVER['HTTP_X_TELEGRAM_INIT_DATA'] ?? ''), envRequired('BOT_TOKEN'), time());
} catch (Throwable) {
    respond(401, ['error' => 'Откройте Mini App заново в Telegram']);
}
try {
    $db = database();
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'GET') {
        // Первый GET может создать пустой черновик; короткая блокировка исключает гонку.
        $db->exec('BEGIN IMMEDIATE');
        try {
            $result = currentDraft($db, $userId);
            $db->exec('COMMIT');
        } catch (Throwable $error) {
            $db->exec('ROLLBACK');
            throw $error;
        }
        respond(200, $result);
    }
    if ($method !== 'POST') {
        respond(405, ['error' => 'Метод не поддерживается']);
    }
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if (!is_string($raw) || strlen($raw) > 65536) {
        respond(413, ['error' => 'Слишком большой запрос']);
    }
    $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($body)) {
        respond(422, ['error' => 'Ожидается объект JSON']);
    }
    respond(200, mutateDraft($db, $userId, objectValue($body)));
} catch (DraftConflict $error) {
    respond(409, ['error' => $error->getMessage()]);
} catch (InvalidArgumentException|JsonException $error) {
    respond(422, ['error' => 'Проверьте формат и поля заявки']);
} catch (Throwable) {
    respond(503,['error' => 'Не удалось подтвердить сохранение']);
}
