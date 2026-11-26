CREATE TABLE slots(id INTEGER PRIMARY KEY,starts_at INTEGER NOT NULL,booking_user INTEGER,booking_key TEXT);
CREATE TABLE waiting(id INTEGER PRIMARY KEY,slot_id INTEGER NOT NULL REFERENCES slots(id),user_id INTEGER NOT NULL,state TEXT NOT NULL);
CREATE UNIQUE INDEX waiting_active ON waiting(slot_id,user_id) WHERE state IN ('waiting','offered');
CREATE TABLE offers(id INTEGER PRIMARY KEY,token TEXT UNIQUE NOT NULL,entry_id INTEGER UNIQUE NOT NULL REFERENCES waiting(id),slot_id INTEGER NOT NULL REFERENCES slots(id),user_id INTEGER NOT NULL,expires_at INTEGER NOT NULL,state TEXT NOT NULL);
CREATE UNIQUE INDEX one_offer ON offers(slot_id) WHERE state='offered';
CREATE TABLE cancellations(booking_key TEXT PRIMARY KEY,slot_id INTEGER NOT NULL,created_at INTEGER NOT NULL);
