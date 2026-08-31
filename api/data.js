// GET /api/data?sheet=Company_Month-Year  (replaces getDataFromSheet)
// Returns rows shaped like the old Sheet: [sl, mobile, name, dept, bill, status, company, sheet, id]
const { sql, ensureSchema, parseSheetName } = require('./_db');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    const sheet = req.query.sheet;
    const p = parseSheetName(sheet);
    if (!p) return res.status(200).json([]);
    const rows = await sql`
      SELECT id, sl_no, mobile, name, dept, bill, status
      FROM bills WHERE company=${p.company} AND period=${p.period} ORDER BY id`;
    const out = rows.map(r => [
      r.sl_no || '', r.mobile || '', r.name || '', r.dept || '',
      (r.bill == null ? '' : r.bill), r.status || 'Pending',
      p.company, sheet, r.id
    ]);
    res.status(200).json(out);
  } catch (e) {
    res.status(200).json([]);
  }
};
