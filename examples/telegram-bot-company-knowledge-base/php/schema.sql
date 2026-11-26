CREATE TABLE IF NOT EXISTS inbox (
    update_id INTEGER PRIMARY KEY,
    payload TEXT NOT NULL,
    state TEXT NOT NULL DEFAULT 'pending',
    created_at INTEGER NOT NULL,
    error_kind TEXT
);
CREATE TABLE IF NOT EXISTS conversations (
    chat_id INTEGER PRIMARY KEY,
    state TEXT NOT NULL DEFAULT 'bot',
    last_question TEXT NOT NULL DEFAULT '',
    version INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chat_id INTEGER NOT NULL,
    question TEXT NOT NULL,
    reason TEXT NOT NULL,
    state TEXT NOT NULL DEFAULT 'open',
    created_at INTEGER NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS one_open_ticket ON tickets(chat_id) WHERE state='open';
