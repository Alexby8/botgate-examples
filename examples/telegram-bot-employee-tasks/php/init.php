<?php

declare(strict_types=1);
require __DIR__.'/common.php';
$db = database();
foreach (['schema.sql','outbox.sql'] as $name) {
    $sql = file_get_contents(__DIR__.'/'.$name);
    if ($sql === false) {
        throw new RuntimeException('Cannot read schema');
    }
    $db->exec($sql);
}
echo "Tables created\n";
