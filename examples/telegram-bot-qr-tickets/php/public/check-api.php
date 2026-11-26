<?php
declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/tickets.php';
$user = authenticatedUser();
try {
    $body = jsonBody();
    $action = $body['action'] ?? null;
    $token = $body['token'] ?? null;
    if (!is_string($token) || strlen($token) > 120 || !in_array($action, ['preview', 'consume'], true)) {
        reply(['error' => 'Неверный запрос'], 422);
    }
    reply(checkTicket(database(), 1, $user, $token, $action === 'consume', time()));
} catch (DomainException $e) {
    reply(['error' => $e->getMessage()], 403);
} catch (InvalidArgumentException|JsonException) {
    reply(['error' => 'Неверный JSON'], 422);
}
