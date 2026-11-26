CREATE TABLE inbox (
    seq INTEGER PRIMARY KEY, update_id INTEGER NOT NULL UNIQUE,
    payload TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'pending', received_at INTEGER NOT NULL
);
CREATE TABLE actions (
    action_key TEXT PRIMARY KEY, method TEXT NOT NULL,
    state TEXT NOT NULL, result TEXT, created_at INTEGER NOT NULL
);

CREATE TABLE chat_requests (
    id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, kind TEXT NOT NULL,
    expires_at INTEGER NOT NULL, state TEXT NOT NULL DEFAULT 'pending', selected_chat INTEGER
);
CREATE TABLE integrations (
    user_id INTEGER PRIMARY KEY, chat_id INTEGER NOT NULL, kind TEXT NOT NULL,
    active INTEGER NOT NULL, confirmed_at INTEGER NOT NULL
);
