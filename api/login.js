// POST /api/login { username, password } -> { success, username, token }
const { sql, ensureSchema, body, verifyPassword, makeToken, logActivity } = require('./_db');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    const b = body(req);
    const username = String(b.username || 'admin').trim();
    const password = String(b.password || '');
    const rows = await sql`SELECT password_hash FROM admins WHERE username=${username}`;
    if (rows.length && verifyPassword(password, rows[0].password_hash)) {
      await logActivity(username, 'login', '');
      return res.status(200).json({ success: true, username, token: makeToken(username, rows[0].password_hash) });
    }
    res.status(200).json({ success: false });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
