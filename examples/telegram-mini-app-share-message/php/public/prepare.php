<?php
declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/telegram.php';
require dirname(__DIR__).'/card.php';
$user = authenticatedUser();
try {
    $body = jsonBody();
    if (!is_string($body['sku'] ?? null) || !preg_match('/^[a-z0-9-]{1,40}$/D', $body['sku'])) {
        reply(['error' => 'Неверный товар'], 422);
    }
    $card = publicCard($body['sku']);
    $result = telegram('savePreparedInlineMessage', preparedPayload($user, $card));
    if ($result['state'] !== 'ok') {
        if (($result['code'] ?? 0) === 429) {
            header('Retry-After: '.($result['retry_after'] ?? 60));
            reply(['error' => 'Слишком много запросов. Попробуйте позже.'], 429);
        }
        reply(['error' => 'Не удалось подготовить карточку. Повторите подготовку.'], 502);
    }
    $prepared = $result['result'] ?? null;
    if (!is_array($prepared) || !is_string($prepared['id'] ?? null) || $prepared['id'] === ''
        || !is_int($prepared['expiration_date'] ?? null) || $prepared['expiration_date'] <= time()) {
        reply(['error' => 'Получен неверный ответ Telegram'], 502);
    }
    reply(['id' => $prepared['id'], 'expires_at' => $prepared['expiration_date'],
        'valid_for' => max(0, $prepared['expiration_date'] - time()), 'preview' => $card['text'], 'url' => $card['url']]);
} catch (DomainException $e) {
    reply(['error' => $e->getMessage()], 404);
} catch (InvalidArgumentException|JsonException) {
    reply(['error' => 'Неверный JSON'], 422);
}
