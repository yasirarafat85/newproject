// Shared helpers for all /api functions.
// Files starting with "_" are NOT treated as routes by Vercel.
const { neon } = require('@neondatabase/serverless');
const crypto = require('crypto');

const sql = neon(process.env.DATABASE_URL);
const SECRET = process.env.SESSION_SECRET || process.env.ADMIN_PASSWORD || 'gpbill-default-secret';

/* ── password hashing (Node crypto, no external dep) ── */
function hashPassword(pw) {
  const salt = crypto.randomBytes(16).toString('hex');
  const h = crypto.scryptSync(String(pw), salt, 32).toString('hex');
  return salt + ':' + h;
}
function verifyPassword(pw, stored) {
  const [salt, h] = String(stored || '').split(':');
  if (!salt || !h) return false;
  const h2 = crypto.scryptSync(String(pw), salt, 32).toString('hex');
  const a = Buffer.from(h), b = Buffer.from(h2);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

/* ── stateless admin session token: base64(username).hmac(username|hash) ── */
function makeToken(username, passwordHash) {
  const sig = crypto.createHmac('sha256', SECRET).update(username + '|' + passwordHash).digest('hex');
  return Buffer.from(String(username)).toString('base64') + '.' + sig;
}

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
  await sql`CREATE TABLE IF NOT EXISTS admins (
    id BIGSERIAL PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT now()
  )`;
  await sql`CREATE TABLE IF NOT EXISTS activity_log (
    id BIGSERIAL PRIMARY KEY,
    username TEXT,
    action TEXT,
    detail TEXT,
    created_at TIMESTAMPTZ DEFAULT now()
  )`;
  await sql`CREATE INDEX IF NOT EXISTS idx_activity_created ON activity_log (created_at DESC)`;
  await sql`CREATE TABLE IF NOT EXISTS backups (
    id BIGSERIAL PRIMARY KEY,
    kind TEXT DEFAULT 'auto',
    data JSONB,
    created_at TIMESTAMPTZ DEFAULT now()
  )`;
  // seed first admin from ADMIN_PASSWORD env (username: admin)
  const cnt = await sql`SELECT COUNT(*)::int AS n FROM admins`;
  if (cnt[0].n === 0) {
    const pw = process.env.ADMIN_PASSWORD || 'admin123';
    await sql`INSERT INTO admins (username, password_hash) VALUES ('admin', ${hashPassword(pw)})`;
  }
  _ready = true;
}

/* ── authenticate an admin request → returns username or null ── */
async function authAdmin(req) {
  const token = req.headers['x-admin-token'];
  if (token) {
    const [ub, sig] = String(token).split('.');
    if (!ub || !sig) return null;
    let username;
    try { username = Buffer.from(ub, 'base64').toString('utf8'); } catch (e) { return null; }
    const rows = await sql`SELECT password_hash FROM admins WHERE username=${username}`;
    if (!rows.length) return null;
    const expect = crypto.createHmac('sha256', SECRET).update(username + '|' + rows[0].password_hash).digest('hex');
    const a = Buffer.from(sig), b = Buffer.from(expect);
    if (a.length === b.length && crypto.timingSafeEqual(a, b)) return username;
    return null;
  }
  // legacy fallback: raw env password header
  const pass = req.headers['x-admin-pass'];
  if (pass && process.env.ADMIN_PASSWORD && pass === process.env.ADMIN_PASSWORD) return 'admin';
  return null;
}

async function logActivity(username, action, detail) {
  try {
    await sql`INSERT INTO activity_log (username, action, detail) VALUES (${username || 'public'}, ${action}, ${detail || ''})`;
  } catch (e) { /* logging must never break the request */ }
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

module.exports = {
  sql, ensureSchema, authAdmin, logActivity, parseSheetName, body,
  hashPassword, verifyPassword, makeToken
};
