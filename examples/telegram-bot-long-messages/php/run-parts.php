<?php
declare(strict_types=1);

require __DIR__.'/common.php';
require __DIR__.'/transport.php';
require __DIR__.'/store-parts.php';
require __DIR__.'/send-next.php';

$batch = $argv[1] ?? throw new InvalidArgumentException('Передайте постоянный ID отчёта');
$db = new PDO('sqlite:'.envRequired('PARTS_DB'), options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA busy_timeout=5000');
$db->exec(stringValue(file_get_contents(__DIR__.'/parts.sql')));
$saved = $db->prepare('SELECT 1 FROM message_parts WHERE batch_id=? LIMIT 1');
$saved->execute([$batch]);
$exists = $saved->fetchColumn() !== false;
$saved->closeCursor();
if (!$exists) {
    $data = json_decode(stringValue(file_get_contents(__DIR__.'/parts.json')), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data) || !array_is_list($data) || $data === []) {
        throw new InvalidArgumentException('Подготовьте непустой parts.json');
    }

    $parts = [];
    foreach ($data as $part) {
        if (!is_array($part) || !is_string($part['text'] ?? null) || ($part['parse_mode'] ?? null) !== 'HTML') {
            throw new InvalidArgumentException('Проверьте формат части');
        }
        $parts[] = ['text' => $part['text'], 'parse_mode' => 'HTML'];
    }
    storeParts($db, $batch, envRequired('TELEGRAM_CHAT_ID'), $parts);
}

do {
    $state = sendNextPart($db, $batch, 'sendTelegram', time());
    if ($state === 'sent') {
        sleep(2);
    }
} while ($state === 'sent');

echo $state.PHP_EOL;
