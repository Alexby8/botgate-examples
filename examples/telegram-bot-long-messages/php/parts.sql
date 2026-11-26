CREATE TABLE IF NOT EXISTS message_parts (
    batch_id TEXT NOT NULL,
    part_no INTEGER NOT NULL,
    payload_json TEXT NOT NULL,
    state TEXT NOT NULL DEFAULT 'pending',
    next_at INTEGER NOT NULL DEFAULT 0,
    attempts INTEGER NOT NULL DEFAULT 0,
    message_id INTEGER,
    PRIMARY KEY (batch_id, part_no)
);
