CREATE TABLE updates(update_id INTEGER PRIMARY KEY);
CREATE TABLE outbox(
 id INTEGER PRIMARY KEY, event_key TEXT UNIQUE NOT NULL, chat_id INTEGER NOT NULL,
 payload TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'pending', attempts INTEGER NOT NULL DEFAULT 0,
 next_at INTEGER NOT NULL DEFAULT 0, message_id INTEGER, error_code INTEGER,
 guard_kind TEXT NOT NULL DEFAULT '', guard_id INTEGER, guard_version INTEGER
);
CREATE INDEX outbox_due ON outbox(state,next_at);
