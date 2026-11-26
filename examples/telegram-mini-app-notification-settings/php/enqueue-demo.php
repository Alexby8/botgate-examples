<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/preferences.php';
if (PHP_SAPI !== 'cli' || !ctype_digit($argv[1] ?? '') || !preg_match('/^[a-z0-9-]{1,40}$/D', $argv[2] ?? '')) {
    exit("Usage: php enqueue-demo.php USER_ID UNIQUE_EVENT_KEY\n");
}
$db = database();
$user = (int) $argv[1];
if (row(sql($db, 'SELECT user_id FROM preferences WHERE user_id=?', [$user])) === null) {
    throw new RuntimeException('Open settings and save preferences first');
}
enqueue($db, $argv[2], $user, 'orders', 'Тестовый заказ готов к выдаче', time(), time() + 86400);
echo "Queued\n";
