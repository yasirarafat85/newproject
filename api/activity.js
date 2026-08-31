// GET /api/activity?limit=50&offset=0 -> { success, data:[{username,action,detail,created_at}] } (admin)
const { sql, ensureSchema, authAdmin } = require('./_db');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    const who = await authAdmin(req);
    if (!who) return res.status(401).json({ success: false, error: 'unauthorized' });
    const limit = Math.min(Math.max(parseInt(req.query.limit) || 50, 1), 200);
    const offset = Math.max(parseInt(req.query.offset) || 0, 0);
    const rows = await sql`
      SELECT username, action, detail, created_at
      FROM activity_log ORDER BY created_at DESC LIMIT ${limit} OFFSET ${offset}`;
    res.status(200).json({ success: true, data: rows });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
