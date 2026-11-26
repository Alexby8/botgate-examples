<?php
declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/preferences.php';
$user = authenticatedUser();
try {
    $body = jsonBody();
    $db = database();
    if (($body['action'] ?? '') === 'save') {
        savePreferences($db, $user, $body, time());
    } elseif (($body['action'] ?? '') !== 'load') {
        throw new InvalidArgumentException('Неизвестная операция');
    }
    $pref = preferences($db, $user, time());
    reply(['settings' => $pref]);
} catch (InvalidArgumentException|JsonException $e) {
    reply(['error' => $e instanceof JsonException ? 'Неверный JSON' : $e->getMessage()], 422);
}
