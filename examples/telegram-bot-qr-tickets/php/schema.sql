CREATE TABLE events (
    id INTEGER PRIMARY KEY, title TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE checkers (
    event_id INTEGER NOT NULL REFERENCES events(id), user_id INTEGER NOT NULL,
    active INTEGER NOT NULL DEFAULT 1, PRIMARY KEY(event_id, user_id)
);
CREATE TABLE tickets (
    id TEXT PRIMARY KEY, event_id INTEGER NOT NULL REFERENCES events(id),
    user_id INTEGER NOT NULL, token_hash TEXT NOT NULL UNIQUE,
    created_at INTEGER NOT NULL, used_at INTEGER, used_by INTEGER,
    cancelled INTEGER NOT NULL DEFAULT 0, UNIQUE(event_id, user_id)
);
CREATE TABLE updates (id INTEGER PRIMARY KEY);
INSERT INTO events(id, title) VALUES (1, 'Встреча разработчиков');
