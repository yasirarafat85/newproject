// GET  /api/balance                 -> { success, balance }   (getBalance)
// POST /api/balance { amount }       -> set balance (admin)    (setBalance)
const { sql, ensureSchema, authAdmin, body, logActivity } = require('./_db');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    if (req.method === 'GET') {
      const r = await sql`SELECT value FROM config WHERE key='balance'`;
      return res.status(200).json({ success: true, balance: Number(r[0] && r[0].value) || 0 });
    }
    if (req.method === 'POST') {
      const who = await authAdmin(req);
      if (!who) return res.status(401).json({ success: false, error: 'unauthorized' });
      const amount = Number(body(req).amount) || 0;
      await sql`INSERT INTO config (key, value) VALUES ('balance', ${amount})
                ON CONFLICT (key) DO UPDATE SET value=${amount}`;
      await logActivity(who, 'balance_set', '৳' + amount);
      return res.status(200).json({ success: true, balance: amount });
    }
    res.status(405).json({ success: false, error: 'method not allowed' });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
