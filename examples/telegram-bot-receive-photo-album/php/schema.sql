
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

CREATE TABLE albums (
    id INTEGER PRIMARY KEY,
    chat_id INTEGER NOT NULL,
    media_group_id TEXT NOT NULL,
    state TEXT NOT NULL DEFAULT 'collecting',
    revision INTEGER NOT NULL DEFAULT 0,
    ready_at INTEGER NOT NULL,
    UNIQUE(chat_id, media_group_id)
);
CREATE TABLE photos (
    chat_id INTEGER NOT NULL,
    message_id INTEGER NOT NULL,
    album_id INTEGER NOT NULL REFERENCES albums(id),
    file_id TEXT NOT NULL,
    PRIMARY KEY(chat_id, message_id)
);
CREATE INDEX photos_album ON photos(album_id, message_id);
CREATE TABLE requests (
    id INTEGER PRIMARY KEY,
    album_id INTEGER NOT NULL UNIQUE REFERENCES albums(id),
    chat_id INTEGER NOT NULL
);
