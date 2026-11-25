<?php
declare(strict_types=1);
require dirname(__DIR__).'/auth.php';
require dirname(__DIR__).'/store.php';
require dirname(__DIR__).'/telegram.php';

/** @param array<string, mixed> $body */
function respond(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Используйте POST']);
}
$type = $_SERVER['CONTENT_TYPE'] ?? '';
if (!is_string($type) || strtolower(trim(explode(';', $type)[0])) !== 'application/json') {
    respond(415, ['error' => 'Ожидается JSON']);
}
$raw = file_get_contents('php://input', false, null, 0, 16385);
if ($raw === false || strlen($raw) > 16384) {
    respond(413, ['error' => 'Запрос слишком большой']);
}
try {
    $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Некорректная форма');
    }
    $userId = validateInitData(is_string($data['initData'] ?? null) ? $data['initData'] : '', botToken(), time());
    foreach (['requestId', 'name', 'question'] as $key) {
        if (!is_string($data[$key] ?? null)) {
            throw new InvalidArgumentException('Заполните поля формы');
        }
    }
    $name = trim($data['name']);
    $question = trim($data['question']);
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di', $data['requestId'])
        || mb_strlen($name) < 1 || mb_strlen($name) > 80
        || mb_strlen($question) < 1 || mb_strlen($question) > 1000) {
        throw new InvalidArgumentException('Имя: 1–80 знаков; вопрос: 1–1000');
    }
    $db = database();
    $saved = saveLead($db, $userId, $data['requestId'], $name, $question);
    $lead = $saved['lead'];
    $notification = $lead['notification'];
    if ($saved['created']) {
        $notification = notifyLead($userId, (int) $lead['id']);
        $query = $db->prepare('UPDATE leads SET notification = ? WHERE id = ?');
        $query->execute([$notification, $lead['id']]);
    }
    respond(200, ['saved' => true, 'id' => (int) $lead['id'], 'notification' => $notification]);
} catch (DomainException) {
    respond(401, ['error' => 'Переоткройте приложение из Telegram']);
} catch (JsonException|InvalidArgumentException $error) {
    respond(422, ['error' => 'Проверьте поля и формат запроса']);
} catch (LogicException) {
    respond(409, ['error' => 'Эта отправка уже сохранена с другими полями. Начните новую заявку']);
} catch (Throwable) {
    // Do not log the token, initData or the user's form here.
    error_log('Mini App submit failed; inspect private application monitoring');
    respond(500, ['error' => 'Не удалось подтвердить результат. Повторите те же данные']);
}
