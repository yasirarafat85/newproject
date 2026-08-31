// GET /api/sheets -> ["IBN_SINA_March-2026", ...]  (replaces getSheetList)
const { sql, ensureSchema } = require('./_db');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    const rows = await sql`SELECT DISTINCT company, period FROM bills`;
    res.status(200).json(rows.map(r => `${r.company}_${r.period}`));
  } catch (e) {
    res.status(200).json([]);
  }
};
