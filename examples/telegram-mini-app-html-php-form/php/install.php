<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__.'/store.php';
if (!is_dir(__DIR__.'/var') && !mkdir(__DIR__.'/var', 0700)) {
    throw new RuntimeException('Cannot create private storage');
}
database()->exec('CREATE TABLE IF NOT EXISTS leads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    request_id TEXT NOT NULL,
    name TEXT NOT NULL,
    question TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    notification TEXT NOT NULL,
    UNIQUE (user_id, request_id)
)');
echo "Storage ready\n";
