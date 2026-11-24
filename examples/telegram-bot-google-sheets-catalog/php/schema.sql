CREATE TABLE catalog (id INTEGER PRIMARY KEY CHECK(id=1), version TEXT NOT NULL, checked_at INTEGER NOT NULL, products TEXT NOT NULL);
CREATE TABLE sync_state (id INTEGER PRIMARY KEY CHECK(id=1), attempted_at INTEGER NOT NULL, error_code TEXT);
