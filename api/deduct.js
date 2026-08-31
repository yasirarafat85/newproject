// POST /api/deduct { amount, companyKey }  (deductBalance)
// Public: triggered when a user marks a bill as paid (auto-deduct) and by admin manual deduct.
const { sql, ensureSchema, body } = require('./_db');

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
    res.status(200).json({ success: true, balance: newBal });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
