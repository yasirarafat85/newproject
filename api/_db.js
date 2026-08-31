// Shared helpers for all /api functions.
// Files starting with "_" are NOT treated as routes by Vercel.
const { neon } = require('@neondatabase/serverless');

const sql = neon(process.env.DATABASE_URL);

let _ready = false;
async function ensureSchema() {
  if (_ready) return;
  await sql`CREATE TABLE IF NOT EXISTS bills (
    id       BIGSERIAL PRIMARY KEY,
    company  TEXT NOT NULL,
    period   TEXT NOT NULL,
    sl_no    TEXT,
    mobile   TEXT,
    name     TEXT,
    dept     TEXT,
    bill     NUMERIC,
    status   TEXT DEFAULT 'Pending'
  )`;
  await sql`CREATE INDEX IF NOT EXISTS idx_bills_company_period ON bills (company, period)`;
  await sql`CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, value NUMERIC)`;
  await sql`CREATE TABLE IF NOT EXISTS company_spending (company TEXT PRIMARY KEY, spent NUMERIC DEFAULT 0)`;
  await sql`INSERT INTO config (key, value) VALUES ('balance', 0) ON CONFLICT (key) DO NOTHING`;
  _ready = true;
}

function checkAdmin(req) {
  const pass = (req.headers['x-admin-pass'] || '').toString();
  const expected = process.env.ADMIN_PASSWORD || 'admin123';
  return expected.length > 0 && pass === expected;
}

// Sheet name "Company_Month-Year" -> { company, period }
function parseSheetName(sheet) {
  const m = String(sheet || '').match(/^(.+)_([A-Za-z]+-\d{4})$/);
  if (!m) return null;
  return { company: m[1], period: m[2] };
}

function body(req) {
  if (!req.body) return {};
  if (typeof req.body === 'string') { try { return JSON.parse(req.body); } catch (e) { return {}; } }
  return req.body;
}

module.exports = { sql, ensureSchema, checkAdmin, parseSheetName, body };
