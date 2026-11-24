<?php

declare(strict_types=1);

require dirname(__DIR__).'/pricing.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo '{"error":"Используйте POST"}';
    exit;
}
try {
    $raw = file_get_contents('php://input', false, null, 0, 8193);
    if ($raw === false || strlen($raw) > 8192) {
        throw new DomainException('Слишком большой запрос');
    }
    $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    if (! is_array($input) || array_is_list($input)) {
        throw new DomainException('Ожидается объект с параметрами');
    }
    /** @var array<string, mixed> $input */
    $params = options($input);
    echo json_encode(['total_minor' => price($params), 'currency' => 'RUB'], JSON_THROW_ON_ERROR);
} catch (DomainException|JsonException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(503);
    echo '{"error":"Расчёт временно недоступен. Попробуйте ещё раз"}';
}
