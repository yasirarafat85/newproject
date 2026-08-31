// POST /api/complete { id }  (replaces markCompletedOnSheet)
const { sql, ensureSchema, body } = require('./_db');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    const { id } = body(req);
    if (!id) return res.status(400).json({ success: false, error: 'no id' });
    await sql`UPDATE bills SET status='Completed' WHERE id=${id}`;
    res.status(200).json({ success: true });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
