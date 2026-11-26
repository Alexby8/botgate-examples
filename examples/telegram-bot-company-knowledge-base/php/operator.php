<?php

declare(strict_types=1);
require __DIR__.'/common.php';
// CLI доступен только сотруднику с доступом к серверу учебного приложения.
$id = filter_var($argv[1] ?? '', FILTER_VALIDATE_INT);
$reply = trim($argv[2] ?? '');
if ($id === false || $id < 1 || $reply === '' || mb_strlen($reply) > 2000) {
    throw new RuntimeException('Usage: php operator.php TICKET_ID "Ответ"');
}
$lock = fopen(envRequired('APP_DB').'.worker.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    throw new RuntimeException('Worker busy; try later');
}
$db = database();
$query = $db->prepare("SELECT * FROM tickets WHERE id=? AND state='open'");
$query->execute([$id]);
$ticket = $query->fetch(PDO::FETCH_ASSOC);
if (!is_array($ticket)) {
    throw new RuntimeException('Open ticket not found');
}
telegram('sendMessage', ['chat_id' => intValue($ticket['chat_id']),'text' => $reply]);
$db->beginTransaction();
$db->prepare("UPDATE tickets SET state='closed' WHERE id=?")->execute([$id]);
$db->prepare("UPDATE conversations SET state='bot',version=version+1 WHERE chat_id=?")->execute([$ticket['chat_id']]);
$db->commit();
echo "Answered and closed\n";
