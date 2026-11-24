<?php

declare(strict_types=1);
require __DIR__.'/domain.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
foreach (['schema.sql', 'outbox.sql'] as $file) {
    $sql = file_get_contents(__DIR__.'/'.$file);
    if ($sql === false) {
        throw new RuntimeException('Missing schema');
    }
    $db->exec($sql);
}
$raw = file_get_contents(__DIR__.'/fixtures/catalog.json');
if ($raw === false) {
    throw new RuntimeException('Fixture missing');
}
$sample = objectValue(json_decode($raw, true, 512, JSON_THROW_ON_ERROR));
replaceCatalog($db, $sample, time());
handle($db, ['update_id' => 1, 'message' => [
    'chat' => ['id' => 42, 'type' => 'private'], 'from' => ['id' => 42], 'text' => '/item mug-450',
]]);
$payload = objectValue(json_decode(stringValue(row($db, 'SELECT payload FROM outbox')['payload'] ?? ''), true, 512, JSON_THROW_ON_ERROR));
echo stringValue($payload['text']).PHP_EOL;
$before = row($db, 'SELECT version FROM catalog');
$invalid = ['values' => [['id', 'title', 'price_minor', 'active', 'stock'], ['mug-450', 'Кружка', -100, true, 12]]];
try {
    replaceCatalog($db, $invalid, time());
    throw new RuntimeException('Invalid snapshot accepted');
} catch (DomainException) {
    if (row($db, 'SELECT version FROM catalog') !== $before) {
        throw new RuntimeException('Working snapshot changed');
    }
    echo "Цена -100 отклонена. Последняя рабочая версия сохранена.\n";
}
echo "DEMO OK — Google, Telegram и рабочая база не использовались\n";
