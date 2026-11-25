CREATE TABLE IF NOT EXISTS form_states (
    bot_id TEXT NOT NULL,
    chat_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    state_json TEXT NOT NULL,
    PRIMARY KEY (bot_id, chat_id, user_id)
);
CREATE TABLE IF NOT EXISTS form_events (
    bot_id TEXT NOT NULL,
    update_id INTEGER NOT NULL,
    PRIMARY KEY (bot_id, update_id)
);
CREATE TABLE IF NOT EXISTS applications (
    bot_id TEXT NOT NULL,
    update_id INTEGER NOT NULL,
    chat_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    data_json TEXT NOT NULL,
    PRIMARY KEY (bot_id, update_id)
);
CREATE TABLE IF NOT EXISTS form_outbox (
    bot_id TEXT NOT NULL,
    update_id INTEGER NOT NULL,
    payload_json TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    attempts INTEGER NOT NULL DEFAULT 0,
    available_at INTEGER NOT NULL DEFAULT 0,
    message_id INTEGER,
    PRIMARY KEY (bot_id, update_id)
);
