CREATE TABLE quotes(
 id TEXT PRIMARY KEY, user_id INTEGER NOT NULL, operation TEXT NOT NULL, input_hash TEXT NOT NULL,
 params TEXT NOT NULL, tariff TEXT NOT NULL, total_minor INTEGER NOT NULL,
 created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL, submitted_at INTEGER,
 UNIQUE(user_id,operation)
);
