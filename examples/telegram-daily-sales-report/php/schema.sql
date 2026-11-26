CREATE TABLE money_events (
 id TEXT PRIMARY KEY, order_id TEXT NOT NULL, kind TEXT NOT NULL CHECK(kind IN ('payment','refund')),
 amount_minor INTEGER NOT NULL CHECK(amount_minor>0), currency TEXT NOT NULL CHECK(currency='RUB'), happened_at INTEGER NOT NULL
);
CREATE INDEX money_period ON money_events(happened_at);
CREATE TABLE leads (id TEXT PRIMARY KEY, created_at INTEGER NOT NULL);
CREATE INDEX leads_period ON leads(created_at);
CREATE TABLE source_cursors (source TEXT PRIMARY KEY, available_from INTEGER NOT NULL, complete_before INTEGER NOT NULL);
CREATE TABLE reports (
 id INTEGER PRIMARY KEY AUTOINCREMENT, report_date TEXT NOT NULL, timezone TEXT NOT NULL, chat_id TEXT NOT NULL,
 period_start INTEGER NOT NULL, period_end INTEGER NOT NULL, text TEXT NOT NULL,
 state TEXT NOT NULL DEFAULT 'pending', attempts INTEGER NOT NULL DEFAULT 0,
 next_attempt_at INTEGER NOT NULL DEFAULT 0, message_id INTEGER, error_code INTEGER,
 UNIQUE(report_date,timezone,chat_id)
);
