// POST /api/complete { id }  (replaces markCompletedOnSheet)
const { sql, ensureSchema, body, logActivity } = require('./_db');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    const { id } = body(req);
    if (!id) return res.status(400).json({ success: false, error: 'no id' });
    const rows = await sql`UPDATE bills SET status='Completed' WHERE id=${id} RETURNING name, mobile, company`;
    const r = rows[0];
    if (r) await logActivity('public', 'bill_paid', (r.name || '') + ' / ' + (r.mobile || '') + (r.company ? ' [' + r.company + ']' : ''));
    res.status(200).json({ success: true });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
