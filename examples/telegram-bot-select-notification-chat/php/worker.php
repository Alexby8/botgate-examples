<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/telegram.php';
require __DIR__.'/runtime.php';
require __DIR__.'/bot.php';
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$lock = fopen(setting('BOT_DB_PATH').'.worker.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
$db = database();
for ($i = 0; $i < 100; ++$i) {
    $entry = row(sql($db, "SELECT * FROM inbox WHERE state='pending' ORDER BY seq LIMIT 1"));
    if ($entry === null) {
        break;
    }
    sql($db, "UPDATE inbox SET state='processing' WHERE seq=?", [(int) $entry['seq']]);
    try {
        $update = json_decode((string) $entry['payload'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($update)) {
            throw new RuntimeException('Invalid stored update');
        }
        handleUpdate($db, $update);
        sql($db, "UPDATE inbox SET state='done',payload='{}' WHERE seq=?", [(int) $entry['seq']]);
    } catch (Throwable) {
        sql($db, "UPDATE inbox SET state='failed' WHERE seq=?", [(int) $entry['seq']]);
        error_log('Update failed: '.(int) $entry['update_id']);
    }
}
flock($lock, LOCK_UN);
