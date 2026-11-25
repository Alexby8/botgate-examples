<?php
declare(strict_types=1);
require __DIR__.'/database.php';
require __DIR__.'/outbox.php';

if (!getenv('BOT_TOKEN')) {
    throw new RuntimeException('Set BOT_TOKEN');
}
$db = database();
for (;;) {
    if (!deliverReply($db, 'consultation', 'telegram', time())) {
        sleep(1);
    }
}
