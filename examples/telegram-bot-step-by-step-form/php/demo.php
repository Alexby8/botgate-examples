<?php
declare(strict_types=1);
require __DIR__.'/store-form.php';
require __DIR__.'/outbox.php';

$db = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$schema = file_get_contents(__DIR__.'/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Cannot read schema.sql');
}
$db->exec($schema);
$now = time();
$script = [
    1 => '/form', 2 => 'Анна', 3 => 'Хочу настроить уведомления',
    4 => '/back', 5 => 'Хочу подключить оплату', 6 => '/confirm',
    7 => '/confirm', 8 => '/form', 9 => 'Вера', 10 => '/cancel',
];
foreach ($script as $id => $text) {
    $update = ['update_id' => $id, 'message' => ['chat' => ['id' => 42, 'type' => 'private'],
        'from' => ['id' => 42], 'text' => $text]];
    applyFormUpdate($db, 'consultation', $update, $now);
    if ($id === 6 && applyFormUpdate($db, 'consultation', $update, $now)) {
        throw new RuntimeException('Повторно обработан тот же update');
    }
    deliverReply($db, 'consultation', static function (string $method, array $payload) use ($id, $text): array {
        echo "> ".$text."\n".$payload['text']."\n\n";
        return ['message_id' => $id];
    }, $now);
}
$query = $db->prepare('SELECT data_json FROM applications');
$query->execute();
$records = $query->fetchAll(PDO::FETCH_COLUMN);
if (count($records) !== 1 || !is_string($records[0]) || json_decode($records[0], true, flags: JSON_THROW_ON_ERROR)
    !== ['name' => 'Анна', 'task' => 'Хочу подключить оплату']) {
    throw new RuntimeException('Неверный результат сохранения');
}
echo "Одна заявка в SQLite: ".$records[0]."\n";
