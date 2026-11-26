<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/reviews.php';
$id = $argv[1] ?? '';
if (!preg_match('/^[A-Z0-9-]{1,40}$/D', $id)) {
    throw new InvalidArgumentException('Usage: php invite.php R-1042');
}
invite(database(), $id);
echo "Invitation queued\n";
