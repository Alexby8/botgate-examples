<?php

declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/domain.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    $user = telegramUser(stringValue($_SERVER['HTTP_X_TELEGRAM_INIT_DATA'] ?? ''), envRequired('BOT_TOKEN'), time());
} catch (Throwable) {
    http_response_code(401);
    echo '{"error":"Откройте Mini App заново"}';
    exit;
}
try {
    $db = database();
    $data = [];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $raw = file_get_contents('php://input');
        if ($raw === false || strlen($raw) > 4096) {
            throw new DomainException('Слишком большой запрос');
        }
        $data = objectValue(json_decode($raw, true, 16, JSON_THROW_ON_ERROR));
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        http_response_code(405);
        exit;
    }
    $result = atomic($db, function () use ($db, $user, $data): array {
        if ($data !== []) {
            requestPoints($db, $user, stringValue($data['order'] ?? ''), intValue($data['points'] ?? -1), time());
        }
        return card($db, $user, time());
    });
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (DomainException|InvalidArgumentException|JsonException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(503);
    echo '{"error":"Не удалось получить данные. Баланс неизвестен"}';
}
