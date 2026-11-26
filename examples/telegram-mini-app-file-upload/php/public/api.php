<?php

declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/uploads.php';
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
    respond(401, ['error' => 'Переоткройте Mini App в Telegram']);
}
try {
    $db = database();
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'GET') {
        $key = stringValue($_GET['operation_id'] ?? '');
        if (!preg_match('/^[a-f0-9-]{36}$/D', $key)) {
            respond(422, ['error' => 'Некорректный ключ']);
        }
        $result = requestStatus($db, $userId, $key);
        respond($result === null ? 404 : 200, $result ?? ['error' => 'Заявка не найдена']);
    }
    if ($method !== 'POST') {
        respond(405, ['error' => 'Метод не поддерживается']);
    }
    if (intValue($_SERVER['CONTENT_LENGTH'] ?? 0) > 20 * 1024 * 1024) {
        respond(413, ['error' => 'Запрос слишком большой']);
    }
    $upload = objectValue($_FILES['files'] ?? []);
    $errors = $upload['error'] ?? null;
    if (!is_array($errors) || !array_is_list($errors) || count($errors) < 1 || count($errors) > 3) {
        respond(422, ['error' => 'Выберите от 1 до 3 файлов']);
    }
    $tmp = $upload['tmp_name'] ?? null;
    if (!is_array($tmp)) {
        respond(422, ['error' => 'Некорректные файлы']);
    }
    $files = [];
    foreach ($errors as $i => $error) {
        $files[] = inspectUpload(['error' => $error,'tmp_name' => $tmp[$i] ?? null]);
    }
    respond(200, saveRequest($db, $userId, stringValue($_POST['operation_id'] ?? ''), stringValue($_POST['description'] ?? ''), $files));
} catch (UploadConflict $e) {
    respond(409, ['error' => $e->getMessage()]);
} catch (InvalidArgumentException $e) {
    respond(422, ['error' => $e->getMessage()]);
} catch (Throwable) {
    respond(503,['error' => 'Не удалось подтвердить сохранение. Проверьте статус заявки']);
}
