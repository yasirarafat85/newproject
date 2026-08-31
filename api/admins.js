// GET    /api/admins            -> { success, data:[{username, created_at}] }  (admin)
// POST   /api/admins {username, password}  -> add admin                        (admin)
// DELETE /api/admins {username}            -> remove admin (never the last)     (admin)
const { sql, ensureSchema, authAdmin, body, hashPassword, logActivity } = require('./_db');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    const who = await authAdmin(req);
    if (!who) return res.status(401).json({ success: false, error: 'unauthorized' });

    if (req.method === 'GET') {
      const rows = await sql`SELECT username, created_at FROM admins ORDER BY created_at`;
      return res.status(200).json({ success: true, data: rows.map(r => ({ username: r.username, created_at: r.created_at })) });
    }

    if (req.method === 'POST') {
      const b = body(req);
      const username = String(b.username || '').trim();
      const password = String(b.password || '');
      if (!username || !password) return res.status(400).json({ success: false, error: 'username ও password দিন' });
      if (password.length < 4) return res.status(400).json({ success: false, error: 'পাসওয়ার্ড কমপক্ষে ৪ অক্ষর' });
      const exists = await sql`SELECT 1 FROM admins WHERE username=${username}`;
      if (exists.length) return res.status(400).json({ success: false, error: 'এই username আগে থেকেই আছে' });
      await sql`INSERT INTO admins (username, password_hash) VALUES (${username}, ${hashPassword(password)})`;
      await logActivity(who, 'admin_add', username);
      return res.status(200).json({ success: true });
    }

    if (req.method === 'DELETE') {
      const username = String(body(req).username || '').trim();
      if (!username) return res.status(400).json({ success: false, error: 'username দিন' });
      const cnt = await sql`SELECT COUNT(*)::int AS n FROM admins`;
      if (cnt[0].n <= 1) return res.status(400).json({ success: false, error: 'শেষ অ্যাডমিন মোছা যাবে না' });
      await sql`DELETE FROM admins WHERE username=${username}`;
      await logActivity(who, 'admin_remove', username);
      return res.status(200).json({ success: true });
    }

    res.status(405).json({ success: false, error: 'method not allowed' });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
