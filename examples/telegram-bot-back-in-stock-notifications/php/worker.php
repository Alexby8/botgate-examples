<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/stock.php';
$db = database();
for ($i = 0; $i < 20 && notifyOne($db, 'telegram'); $i++) {
    sleep(1);
}
