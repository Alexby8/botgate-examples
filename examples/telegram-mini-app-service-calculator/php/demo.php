<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/outbox.php';
require __DIR__.'/domain.php';

// Отдельная база в памяти: не открываем APP_DB, не вызываем Telegram.
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
foreach (['schema.sql', 'outbox.sql'] as $file) {
    $sql = file_get_contents(__DIR__.'/'.$file);
    if ($sql === false) {
        throw new RuntimeException('Missing schema');
    }
    $db->exec($sql);
}
putenv('MANAGER_CHAT_ID=900000001');
$input = ['qty' => 100, 'size' => 'A4', 'color' => 'bw', 'urgent' => 'no', 'delivery' => 'pickup', 'operation' => 'demo-order-0001'];
if (price(options($input)) !== 135000) {
    throw new RuntimeException('Base price mismatch');
}
echo "100 чёрно-белых A4, самовывоз: 1350,00 ₽\n";
$input['urgent'] = 'yes';
$input['delivery'] = 'courier';
$quote = atomic($db, fn (): array => saveQuote($db, 42, $input, 1700000000));
if (intValue($quote['total_minor']) !== 203750) {
    throw new RuntimeException('Urgent price mismatch');
}
echo "Срочно, с курьером: 2037,50 ₽\n";
$again = atomic($db, fn (): array => saveQuote($db, 42, $input, 1700000001));
if ($again['id'] !== $quote['id']) {
    throw new RuntimeException('Duplicate quote');
}
for ($i = 0; $i < 2; $i++) {
    atomic($db, fn () => submitQuote($db, 42, stringValue($quote['id']), 1700000002));
}
if (intValue(row($db, 'SELECT COUNT(*) AS n FROM outbox')['n'] ?? 0) !== 1) {
    throw new RuntimeException('Duplicate application');
}
echo "Повторное сохранение: тот же расчёт\nПовторная заявка: одно уведомление в очереди\nDEMO OK — сеть и рабочая база не использовались\n";
