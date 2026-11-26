<?php
declare(strict_types=1);
require __DIR__.'/common.php';
if (PHP_SAPI !== 'cli' || !ctype_digit($argv[1] ?? '') || !in_array($argv[2] ?? '', ['on', 'off'], true)) {
    exit("Usage: php checker-admin.php TELEGRAM_USER_ID on|off\n");
}
sql(database(), 'INSERT INTO checkers(event_id,user_id,active) VALUES(1,?,?)
    ON CONFLICT(event_id,user_id) DO UPDATE SET active=excluded.active',
    [(int) $argv[1], $argv[2] === 'on' ? 1 : 0]);
echo "Checker updated\n";
