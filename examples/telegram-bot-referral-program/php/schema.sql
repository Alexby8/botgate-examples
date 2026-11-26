CREATE TABLE members (
    user_id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE, joined_at INTEGER NOT NULL
);
CREATE TABLE referrals (
    invitee INTEGER PRIMARY KEY REFERENCES members(user_id),
    inviter INTEGER NOT NULL REFERENCES members(user_id),
    bound_at INTEGER NOT NULL, CHECK(invitee <> inviter)
);
CREATE TABLE orders (
    id TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES members(user_id),
    amount INTEGER NOT NULL CHECK(amount > 0), paid_at INTEGER NOT NULL,
    status TEXT NOT NULL CHECK(status IN ('paid','refunded'))
);
CREATE TABLE rewards (
    id INTEGER PRIMARY KEY, program TEXT NOT NULL,
    invitee INTEGER NOT NULL, inviter INTEGER NOT NULL,
    order_id TEXT NOT NULL REFERENCES orders(id), points INTEGER NOT NULL,
    created_at INTEGER NOT NULL, reversed_at INTEGER,
    UNIQUE(program, invitee)
);
CREATE TABLE reward_log (
    id INTEGER PRIMARY KEY, reward_id INTEGER NOT NULL REFERENCES rewards(id),
    user_id INTEGER NOT NULL, delta INTEGER NOT NULL,
    action TEXT NOT NULL CHECK(action IN ('award','reverse')), created_at INTEGER NOT NULL,
    UNIQUE(reward_id, action)
);
CREATE TABLE updates (id INTEGER PRIMARY KEY);
