<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/telegram.php';
require __DIR__.'/runtime.php';
require __DIR__.'/bot.php';
if (PHP_SAPI !== 'cli' || !ctype_digit($argv[1] ?? '') || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $argv[2] ?? '')) {
    exit("Usage: php notify.php OWNER_TELEGRAM_ID EVENT_ID\n");
}
// Share the same lock with worker.php: disconnect and sending must not run in parallel.
$lock = fopen(setting('BOT_DB_PATH').'.worker.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX)) {
    exit(1);
}
$db = database(); $user = (int) $argv[1];
$target = row(sql($db, 'SELECT * FROM integrations WHERE user_id=? AND active=1', [$user]));
if ($target === null) {
    exit("No active integration\n");
}
try {
    checkDestination($user, (int) $target['chat_id'], (string) $target['kind']);
} catch (Throwable) {
    sql($db, 'UPDATE integrations SET active=0 WHERE user_id=?', [$user]);
    exit("Integration suspended; check access and reconnect\n");
}
$result = telegramOnce($db, 'notice:'.$user.':'.$argv[2], 'sendMessage', [
    'chat_id' => (int) $target['chat_id'], 'text' => 'Тестовое уведомление: новая заявка в вашем сервисе.',
]);
echo $result['state']."\n";
