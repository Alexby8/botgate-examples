<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/stock.php';
$user = filter_var($argv[1] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($user === false) throw new InvalidArgumentException('Передайте ID своего тестового чата');
$reply = telegram('sendMessage', watchCard(database(), $user, 1));
echo $reply['state'].PHP_EOL;
