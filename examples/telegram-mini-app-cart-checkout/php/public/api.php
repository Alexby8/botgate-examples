<?php

declare(strict_types=1);

require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/app.php';

$raw = $_SERVER['HTTP_X_TELEGRAM_INIT_DATA'] ?? '';
try {
    $user = miniUser(is_string($raw) ? $raw : '', setting('BOT_TOKEN'), time());
} catch (InvalidArgumentException|JsonException) {
    reply(['error'=>'Откройте Mini App из меню бота заново.'],401);
}
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'GET' && $method !== 'POST') {
    reply(['error'=>'Unsupported method'],405);
}
try {
    $db = database();
    if ($method === 'GET') {
        reply(atomic($db, fn () => state($db,$user)));
    }
    $body = jsonBody();
    $result = atomic($db, function () use ($db,$user,$body): array {
        $action = $body['action'] ?? null;
        if ($action === 'place') {
            $quote = $body['quote'] ?? null;
            $op = $body['operation'] ?? null;
            if (!is_string($quote) || !preg_match('/^[a-f0-9]{32}$/D',$quote)
                || !is_string($op) || !preg_match('/^[a-f0-9-]{36}$/D',$op)) {
                throw new InvalidArgumentException('Неверный ключ заказа.');
            }
            return place($db,$user,$quote,$op,time());
        }
        $expected = $body['revision'] ?? null;
        if (!is_int($expected) || $expected < 0) {
            throw new InvalidArgumentException('Отсутствует версия корзины.');
        }
        if ($action === 'preview') {
            return preview($db,$user,$expected,time());
        }
        $product = $body['product'] ?? null;
        $quantity = $body['quantity'] ?? null;
        if ($action !== 'quantity' || !is_int($product) || !is_int($quantity)) {
            throw new InvalidArgumentException('Проверьте товар и количество.');
        }
        setQuantity($db,$user,$expected,$product,$quantity);
        return state($db,$user);
    });
    reply($result);
} catch (Conflict $e) {
    reply(['error'=>$e->getMessage()],409);
} catch (InvalidArgumentException|JsonException $e) {
    reply(['error'=>'Неверный запрос. Проверьте товар и количество.'],422);
} catch (Throwable) {
    reply(['error'=>'Результат запроса неизвестен.'],503);
}
