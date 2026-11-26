
CREATE TABLE inbox (
    update_id INTEGER PRIMARY KEY,
    payload TEXT NOT NULL,
    state TEXT NOT NULL DEFAULT 'pending'
);
CREATE TABLE outbox (
    id INTEGER PRIMARY KEY,
    method TEXT NOT NULL,
    payload TEXT NOT NULL,
    state TEXT NOT NULL DEFAULT 'pending',
    next_attempt INTEGER NOT NULL DEFAULT 0,
    attempts INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE slots (
    id INTEGER PRIMARY KEY,
    starts_at INTEGER NOT NULL UNIQUE,
    local_day TEXT NOT NULL,
    state TEXT NOT NULL DEFAULT 'free' CHECK(state IN ('free','held','booked')),
    user_id INTEGER,
    booking_key TEXT,
    expires_at INTEGER
);
