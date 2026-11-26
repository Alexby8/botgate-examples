<?php

declare(strict_types=1);
require __DIR__.'/domain.php';
$lock = fopen(envRequired('APP_DB').'.poll.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
try {
    $feed = envRequired('FEED_URL');
    ingest(database(), $feed, fetchFeed($feed), envRequired('CHANNEL_ID'), getenv('KEYWORD') ?: '', time());
    echo "Feed checked\n";
} catch (Throwable) {
    // На первом сбое не создаём feeds: следующий успех ещё должен быть baseline.
    run(database(), 'UPDATE feeds SET error_code=? WHERE url=?', ['FEED_FAILED', envRequired('FEED_URL')]);
    fwrite(STDERR, "Feed failed; previous queue retained\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
