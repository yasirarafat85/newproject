// POST /api/deduct { amount, companyKey }  (deductBalance)
// Public: auto-deduct on bill payment, and admin manual deduct.
const { sql, ensureSchema, authAdmin, body, logActivity } = require('./_db');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    const b = body(req);
    const amount = Number(b.amount) || 0;
    const companyKey = b.companyKey ? String(b.companyKey) : '';
    const r = await sql`UPDATE config SET value = value - ${amount} WHERE key='balance' RETURNING value`;
    const newBal = Number(r[0] && r[0].value) || 0;
    if (companyKey && amount > 0) {
      await sql`INSERT INTO company_spending (company, spent) VALUES (${companyKey}, ${amount})
                ON CONFLICT (company) DO UPDATE SET spent = company_spending.spent + ${amount}`;
    }
    // manual deducts carry a companyKey like "manual_<note>"; log who when known
    const who = await authAdmin(req).catch(() => null);
    const isManual = companyKey.indexOf('manual_') === 0;
    await logActivity(who || 'public', isManual ? 'manual_deduct' : 'auto_deduct', '৳' + amount + (companyKey ? ' (' + companyKey + ')' : ''));
    res.status(200).json({ success: true, balance: newBal });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
