CREATE TABLE feeds (url TEXT PRIMARY KEY, checked_at INTEGER NOT NULL, error_code TEXT);
CREATE TABLE entries (
 feed TEXT NOT NULL, entry_key TEXT NOT NULL, title TEXT NOT NULL, link TEXT NOT NULL,
 published INTEGER NOT NULL, state TEXT NOT NULL, due INTEGER,
 PRIMARY KEY(feed,entry_key)
);
