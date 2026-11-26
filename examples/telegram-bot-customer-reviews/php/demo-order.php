<?php

declare(strict_types=1);
require __DIR__.'/common.php';
$user = intValue($argv[1] ?? '');
if ($user <= 0) {
    throw new InvalidArgumentException('Use your test Telegram user ID');
}
database()->prepare("INSERT INTO orders(id,user_id,status,completed_at) VALUES('R-1042',?,'completed',?)")->execute([$user,time()]);
echo "Test order R-1042 created\n";
