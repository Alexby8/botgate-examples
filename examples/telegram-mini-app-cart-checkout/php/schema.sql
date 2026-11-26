
CREATE TABLE products (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    price_minor INTEGER NOT NULL CHECK(price_minor BETWEEN 0 AND 100000000),
    stock INTEGER NOT NULL CHECK(stock >= 0)
);
CREATE TABLE carts (user_id INTEGER PRIMARY KEY, revision INTEGER NOT NULL DEFAULT 0);
CREATE TABLE cart_items (
    user_id INTEGER NOT NULL REFERENCES carts(user_id),
    product_id INTEGER NOT NULL REFERENCES products(id),
    quantity INTEGER NOT NULL CHECK(quantity BETWEEN 1 AND 20),
    PRIMARY KEY(user_id, product_id)
);
CREATE TABLE quotes (
    id TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES carts(user_id),
    revision INTEGER NOT NULL,
    payload TEXT NOT NULL,
    expires_at INTEGER NOT NULL
);
CREATE TABLE orders (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES carts(user_id),
    quote_id TEXT NOT NULL UNIQUE REFERENCES quotes(id),
    operation_key TEXT NOT NULL,
    total_minor INTEGER NOT NULL,
    payload TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    UNIQUE(user_id, operation_key)
);
INSERT INTO products(id,name,price_minor,stock) VALUES(1,'Зелёный чай',45000,12),(2,'Травяной сбор',32000,5);
