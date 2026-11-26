<?php

declare(strict_types=1);
require __DIR__.'/common.php';
$id = intValue($argv[1] ?? '');
$action = $argv[2] ?? '';
if (!in_array($action, ['retry','confirmed-sent'], true)) {
    throw new RuntimeException('Usage: php review.php ID retry|confirmed-sent');
}
$lock = fopen(envRequired('APP_DB').'.send.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    throw new RuntimeException('Sender busy');
}
$q = database()->prepare("UPDATE reports SET state=?,attempts=0,next_attempt_at=0 WHERE id=? AND state IN ('sending','unknown','failed')");
$q->execute([$action === 'retry' ? 'pending' : 'sent',$id]);
echo 'Updated rows: '.$q->rowCount().PHP_EOL;
