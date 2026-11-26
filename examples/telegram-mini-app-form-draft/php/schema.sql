CREATE TABLE IF NOT EXISTS drafts (
    user_id INTEGER PRIMARY KEY,
    draft_id TEXT NOT NULL UNIQUE,
    revision INTEGER NOT NULL,
    state TEXT NOT NULL,
    fields TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    application_id INTEGER
);
CREATE TABLE IF NOT EXISTS applications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    draft_id TEXT NOT NULL,
    fields TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    UNIQUE(user_id,draft_id)
);
CREATE TABLE IF NOT EXISTS operations (
    user_id INTEGER NOT NULL,
    operation_id TEXT NOT NULL,
    request_hash TEXT NOT NULL,
    response TEXT NOT NULL,
    PRIMARY KEY(user_id,operation_id)
);
