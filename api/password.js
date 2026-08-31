// POST /api/password { oldPassword, newPassword }  -> change own password (admin)
// Returns a fresh token because the old one is invalidated by the hash change.
const { sql, ensureSchema, authAdmin, body, hashPassword, verifyPassword, makeToken, logActivity } = require('./_db');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    const who = await authAdmin(req);
    if (!who) return res.status(401).json({ success: false, error: 'unauthorized' });
    const b = body(req);
    const oldPassword = String(b.oldPassword || '');
    const newPassword = String(b.newPassword || '');
    if (newPassword.length < 4) return res.status(400).json({ success: false, error: 'নতুন পাসওয়ার্ড কমপক্ষে ৪ অক্ষর' });
    const rows = await sql`SELECT password_hash FROM admins WHERE username=${who}`;
    if (!rows.length || !verifyPassword(oldPassword, rows[0].password_hash)) {
      return res.status(400).json({ success: false, error: 'বর্তমান পাসওয়ার্ড ভুল' });
    }
    const newHash = hashPassword(newPassword);
    await sql`UPDATE admins SET password_hash=${newHash} WHERE username=${who}`;
    await logActivity(who, 'password_change', '');
    res.status(200).json({ success: true, token: makeToken(who, newHash) });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
