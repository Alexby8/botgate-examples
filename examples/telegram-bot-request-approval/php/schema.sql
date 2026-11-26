
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

CREATE TABLE approvers (user_id INTEGER PRIMARY KEY, limit_minor INTEGER NOT NULL, active INTEGER NOT NULL DEFAULT 1);
CREATE TABLE requests (
    id INTEGER PRIMARY KEY, version INTEGER NOT NULL DEFAULT 1, title TEXT NOT NULL, amount_minor INTEGER NOT NULL,
    author INTEGER NOT NULL, approver INTEGER NOT NULL REFERENCES approvers(user_id),
    status TEXT NOT NULL DEFAULT 'pending', reason TEXT, card_message_id INTEGER
);
CREATE TABLE drafts (
    request_id INTEGER PRIMARY KEY REFERENCES requests(id), version INTEGER NOT NULL, approver INTEGER NOT NULL,
    expires_at INTEGER NOT NULL, nonce TEXT NOT NULL, prompt_id INTEGER, UNIQUE(approver,prompt_id)
);
CREATE TABLE decisions (
    request_id INTEGER NOT NULL REFERENCES requests(id), version INTEGER NOT NULL, actor INTEGER NOT NULL,
    status TEXT NOT NULL, reason TEXT, decided_at INTEGER NOT NULL, PRIMARY KEY(request_id,version)
);
