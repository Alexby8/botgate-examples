<?php

declare(strict_types=1);
require __DIR__.'/domain.php';

$db = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
foreach (['schema.sql', 'outbox.sql'] as $file) {
    $db->exec(stringValue(file_get_contents(__DIR__.'/'.$file)));
}
$feed = 'https://news.example.org/rss.xml';
$now = time();
$runs = ['01-baseline' => 0, '02-new' => 1, '03-edited' => 1];
foreach ($runs as $name => $expected) {
    $xml = stringValue(file_get_contents(__DIR__.'/fixtures/'.$name.'.xml'));
    ingest($db, $feed, $xml, '@example_test_channel', 'Telegram', $now);
    $queued = intValue(row($db, 'SELECT COUNT(*) AS total FROM outbox')['total'] ?? null);
    if ($queued !== $expected) {
        throw new RuntimeException('Unexpected queue size');
    }
    echo $name.': '.$queued." in outbox\n";
}
$job = row($db, 'SELECT payload FROM outbox');
$payload = objectValue(json_decode(stringValue($job['payload'] ?? null), true, flags: JSON_THROW_ON_ERROR));
echo "\n".stringValue($payload['text'] ?? null)."\n";
