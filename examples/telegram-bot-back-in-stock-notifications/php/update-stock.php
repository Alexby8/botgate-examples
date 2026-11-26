<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/stock.php';
$amount = filter_var($argv[1] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$version = filter_var($argv[2] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($amount === false || $version === false) throw new InvalidArgumentException('Нужны остаток и версия');
echo updateStock(database(), 1, $amount, $version) ? "Остаток применён\n" : "Повтор или старая версия\n";
