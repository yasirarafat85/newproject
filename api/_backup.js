// Shared backup helpers (underscore = not a route).
const { sql } = require('./_db');

async function snapshot() {
  const bills = await sql`SELECT company, period, sl_no, mobile, name, dept, bill, status FROM bills ORDER BY id`;
  const config = await sql`SELECT key, value FROM config`;
  const spending = await sql`SELECT company, spent FROM company_spending`;
  return { bills, config, company_spending: spending, at: new Date().toISOString() };
}

async function createBackup(kind) {
  const data = await snapshot();
  await sql`INSERT INTO backups (kind, data) VALUES (${kind || 'manual'}, ${JSON.stringify(data)})`;
  return data.bills.length;
}

// keep only the newest N auto backups
async function pruneAuto(keep) {
  await sql`
    DELETE FROM backups WHERE kind='auto' AND id NOT IN (
      SELECT id FROM backups WHERE kind='auto' ORDER BY created_at DESC LIMIT ${keep}
    )`;
}

module.exports = { snapshot, createBackup, pruneAuto };
