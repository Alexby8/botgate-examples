
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

CREATE TABLE categories (id INTEGER PRIMARY KEY, version INTEGER NOT NULL DEFAULT 1);
CREATE TABLE products (
    id INTEGER PRIMARY KEY, category_id INTEGER NOT NULL REFERENCES categories(id),
    title TEXT NOT NULL, price_minor INTEGER NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL
);
CREATE INDEX catalog_order ON products(category_id,is_active,sort_order,id);
CREATE TABLE sessions (
    token TEXT PRIMARY KEY, chat_id INTEGER NOT NULL UNIQUE,
    category_id INTEGER NOT NULL REFERENCES categories(id), catalog_version INTEGER NOT NULL,
    expires_at INTEGER NOT NULL, revision INTEGER NOT NULL DEFAULT 1,
    message_id INTEGER, page INTEGER NOT NULL DEFAULT 0
);
INSERT INTO categories(id) VALUES(1),(2);
INSERT INTO products(category_id,title,price_minor,sort_order) VALUES
    (1,'Блокнот',39000,1),(1,'Ручка',9000,2),(1,'Карандаш',5000,3),
    (1,'Папка',16000,4),(1,'Ластик',4000,5),(1,'Линейка',8000,6);
