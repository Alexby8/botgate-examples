<?php

declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/outbox.php';
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
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $result = ['quotes' => run($db, 'SELECT * FROM quotes WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT 10', [$user])->fetchAll()];
    } else {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            exit;
        }
        $raw = file_get_contents('php://input');
        if ($raw === false || strlen($raw) > 8192) {
            throw new DomainException('Слишком большой запрос');
        }
        $data = objectValue(json_decode($raw, true, 16, JSON_THROW_ON_ERROR));
        $result = atomic($db, function () use ($db, $user, $data): array {
            if (($data['action'] ?? '') === 'quote') {
                return ['quote' => saveQuote($db, $user, $data, time())];
            }
            if (($data['action'] ?? '') === 'submit') {
                submitQuote($db, $user, stringValue($data['id'] ?? ''), time());
                return ['accepted' => true];
            }
            throw new DomainException('Неизвестное действие');
        });
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (DomainException|InvalidArgumentException|JsonException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(503);
    echo '{"error":"Сервис временно недоступен. Повторите тот же запрос"}';
}
