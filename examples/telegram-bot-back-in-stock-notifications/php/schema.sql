CREATE TABLE products (
  id INTEGER PRIMARY KEY, title TEXT NOT NULL, url TEXT NOT NULL,
  available INTEGER NOT NULL DEFAULT 0 CHECK (available >= 0),
  source_version INTEGER NOT NULL DEFAULT 0, episode INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE watches (
  id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL REFERENCES products(id),
  generation INTEGER NOT NULL DEFAULT 1, active INTEGER NOT NULL DEFAULT 1,
  UNIQUE (user_id, product_id)
);
CREATE TABLE watch_events (update_id INTEGER PRIMARY KEY);
CREATE TABLE stock_deliveries (
  id INTEGER PRIMARY KEY, watch_id INTEGER NOT NULL REFERENCES watches(id),
  generation INTEGER NOT NULL, episode INTEGER NOT NULL,
  state TEXT NOT NULL DEFAULT 'pending', ready_at INTEGER NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 0, started_at INTEGER, message_id INTEGER,
  UNIQUE (watch_id, generation, episode)
);
CREATE INDEX stock_ready ON stock_deliveries(state, ready_at);
INSERT INTO products (id, title, url) VALUES
  (1, 'Зелёный чай, 250 г', 'https://shop.example.com/products/green-tea');
