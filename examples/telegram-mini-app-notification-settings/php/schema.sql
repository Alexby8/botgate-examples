CREATE TABLE preferences (
    user_id INTEGER PRIMARY KEY, orders INTEGER NOT NULL DEFAULT 0,
    offers INTEGER NOT NULL DEFAULT 0, timezone TEXT NOT NULL DEFAULT 'UTC',
    quiet INTEGER NOT NULL DEFAULT 0, start_min INTEGER NOT NULL DEFAULT 1320,
    end_min INTEGER NOT NULL DEFAULT 480, blocked INTEGER NOT NULL DEFAULT 0,
    updated_at INTEGER NOT NULL
);
CREATE TABLE notifications (
    id INTEGER PRIMARY KEY, event_key TEXT NOT NULL UNIQUE,
    user_id INTEGER NOT NULL REFERENCES preferences(user_id),
    category TEXT NOT NULL CHECK(category IN ('orders','offers')),
    body TEXT NOT NULL, due_at INTEGER NOT NULL, expires_at INTEGER NOT NULL,
    state TEXT NOT NULL DEFAULT 'pending', attempts INTEGER NOT NULL DEFAULT 0,
    claimed_at INTEGER, message_id INTEGER
);
CREATE INDEX notifications_due ON notifications(state,due_at);
CREATE TABLE updates (id INTEGER PRIMARY KEY);
