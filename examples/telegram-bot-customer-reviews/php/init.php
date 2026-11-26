<?php

declare(strict_types=1);
require __DIR__.'/common.php';
database()->exec(stringValue(file_get_contents(__DIR__.'/schema.sql')));
echo "Database ready\n";
