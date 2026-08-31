-- GP Bill Tracker — Neon (Postgres) schema
-- Neon SQL Editor-এ একবার Run করুন (অথবা অ্যাপ প্রথমবার চললে নিজে থেকেই তৈরি হয়ে যাবে)।

CREATE TABLE IF NOT EXISTS bills (
  id       BIGSERIAL PRIMARY KEY,
  company  TEXT NOT NULL,        -- e.g. IBN_SINA
  period   TEXT NOT NULL,        -- e.g. March-2026
  sl_no    TEXT,
  mobile   TEXT,
  name     TEXT,
  dept     TEXT,
  bill     NUMERIC,
  status   TEXT DEFAULT 'Pending'
);

CREATE INDEX IF NOT EXISTS idx_bills_company_period ON bills (company, period);

CREATE TABLE IF NOT EXISTS config (
  key   TEXT PRIMARY KEY,
  value NUMERIC
);

INSERT INTO config (key, value) VALUES ('balance', 0)
  ON CONFLICT (key) DO NOTHING;

CREATE TABLE IF NOT EXISTS company_spending (
  company TEXT PRIMARY KEY,
  spent   NUMERIC DEFAULT 0
);
