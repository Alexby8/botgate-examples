<?php
declare(strict_types=1);
require __DIR__.'/split-text.php';

$text = file_get_contents(__DIR__.'/report.txt');
if ($text === false) {
    throw new RuntimeException('Не найден report.txt');
}
$parts = splitTelegramText($text);
$payloads = [];
foreach ($parts as $index => $part) {
    $header = 'Отчёт — часть '.($index + 1).' из '.count($parts);
    if (utf16Length($header."\n".$part) > 4096) {
        throw new LengthException('Сообщение с заголовком слишком длинное');
    }
    $payloads[] = [
        'text' => '<b>'.htmlspecialchars($header, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</b>\n"
            .htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'parse_mode' => 'HTML',
    ];
}
echo json_encode($payloads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
