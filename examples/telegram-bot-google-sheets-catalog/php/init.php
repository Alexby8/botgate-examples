<?php

declare(strict_types=1);
require __DIR__.'/common.php';
$db = database();
atomic($db, function () use ($db): void {
    foreach (['schema.sql', 'outbox.sql'] as $file) {
        $sql = file_get_contents(__DIR__.'/'.$file);
        if ($sql === false) {
            throw new RuntimeException('Schema missing');
        }
        $db->exec($sql);
    }
});
echo "Created empty application database\n";
