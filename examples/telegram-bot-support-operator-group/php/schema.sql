
CREATE TABLE inbox (
    update_id INTEGER PRIMARY KEY,
    payload TEXT NOT NULL,
    state TEXT NOT NULL DEFAULT 'pending'
);
CREATE TABLE outbox (
    id INTEGER PRIMARY KEY,
    method TEXT NOT NULL,
    payload TEXT NOT NULL,
    binding TEXT,
    state TEXT NOT NULL DEFAULT 'pending',
    next_attempt INTEGER NOT NULL DEFAULT 0,
    attempts INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE tickets (
    id INTEGER PRIMARY KEY,
    client INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    closed_at INTEGER,
    closed_by INTEGER
);
CREATE UNIQUE INDEX single_open_ticket ON tickets(client) WHERE closed_at IS NULL;
CREATE TABLE message_links (
    chat_id INTEGER NOT NULL,
    message_id INTEGER NOT NULL,
    ticket_id INTEGER NOT NULL REFERENCES tickets(id),
    PRIMARY KEY(chat_id, message_id)
);
