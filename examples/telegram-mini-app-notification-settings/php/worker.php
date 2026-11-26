<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/telegram.php';
require __DIR__.'/preferences.php';
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$db = database();
for ($i = 0; $i < 20; ++$i) {
    $job = claimNotification($db, time());
    if ($job === null) {
        continue;
    }
    $result = telegram('sendMessage', ['chat_id' => (int) $job['user_id'], 'text' => (string) $job['body']]);
    atomic($db, function () use ($db, $job, $result): void {
        $state = 'unknown';
        $due = time();
        $messageId = null;
        if ($result['state'] === 'ok') {
            $value = $result['result'] ?? null;
            $messageId = is_array($value) && is_int($value['message_id'] ?? null) ? $value['message_id'] : null;
            $state = $messageId === null ? 'unknown' : 'sent';
        } elseif ($result['state'] === 'rejected') {
            $state = 'failed';
            if (($result['code'] ?? 0) === 403) {
                $state = 'blocked';
                sql($db, 'UPDATE preferences SET blocked=1 WHERE user_id=?', [(int) $job['user_id']]);
            } elseif (($result['code'] ?? 0) === 429 && (int) $job['attempts'] < 4) {
                $state = 'pending';
                $due += $result['retry_after'] ?? 60;
            }
        }
        sql($db, 'UPDATE notifications SET state=?,due_at=?,message_id=? WHERE id=?',
            [$state, $due, $messageId, (int) $job['id']]);
    });
    if (($result['code'] ?? 0) === 429) {
        break;
    }
    sleep(1);
}
