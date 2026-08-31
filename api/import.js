// POST /api/import { rows, sheetName }  (admin)  (importCSVToSheet)
// rows: array of [sl, mobile, name, dept, bill, status]
// Replaces all rows for that company+period.
const { sql, ensureSchema, authAdmin, parseSheetName, body, logActivity } = require('./_db');

function num(v) {
  const n = parseFloat(String(v == null ? '' : v).replace(/[^0-9.]/g, ''));
  return isNaN(n) ? null : n;
}

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    const who = await authAdmin(req);
    if (!who) return res.status(401).json({ success: false, error: 'unauthorized' });
    const { rows, sheetName } = body(req);
    const p = parseSheetName(sheetName);
    if (!p) return res.status(400).json({ success: false, error: 'invalid sheet name' });
    if (!Array.isArray(rows) || !rows.length) return res.status(400).json({ success: false, error: 'no rows' });

    await sql`DELETE FROM bills WHERE company=${p.company} AND period=${p.period}`;

    const CHUNK = 500;
    let added = 0;
    for (let i = 0; i < rows.length; i += CHUNK) {
      const slice = rows.slice(i, i + CHUNK);
      const queries = slice.map(r => sql`
        INSERT INTO bills (company, period, sl_no, mobile, name, dept, bill, status)
        VALUES (${p.company}, ${p.period}, ${String(r[0] || '')}, ${String(r[1] || '')},
                ${String(r[2] || '')}, ${String(r[3] || '')}, ${num(r[4])}, ${String(r[5] || 'Pending')})`);
      await sql.transaction(queries);
      added += slice.length;
    }
    await logActivity(who, 'import', sheetName + ' (' + added + ' rows)');
    res.status(200).json({ success: true, rowsAdded: added, sheetName });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
