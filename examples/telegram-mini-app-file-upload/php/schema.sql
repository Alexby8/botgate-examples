CREATE TABLE requests (
 id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
 operation_id TEXT NOT NULL, request_hash TEXT NOT NULL, description TEXT NOT NULL,
 created_at INTEGER NOT NULL, UNIQUE(user_id,operation_id)
);
CREATE TABLE attachments (
 id TEXT PRIMARY KEY, request_id INTEGER NOT NULL REFERENCES requests(id),
 storage_name TEXT NOT NULL UNIQUE, mime TEXT NOT NULL, bytes INTEGER NOT NULL,
 sha256 TEXT NOT NULL
);
