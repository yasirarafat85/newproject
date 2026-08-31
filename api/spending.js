// GET  /api/spending                 -> { success, data:[{company,spent}] }  (getCompanySpending)
// POST /api/spending { company }      -> reset that company's spend (admin)   (resetCompanySpending)
const { sql, ensureSchema, checkAdmin, body } = require('./_db');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    if (req.method === 'GET') {
      const rows = await sql`SELECT company, spent FROM company_spending WHERE company <> '' ORDER BY spent DESC`;
      return res.status(200).json({
        success: true,
        data: rows.map(r => ({ company: String(r.company), spent: Number(r.spent) || 0 }))
      });
    }
    if (req.method === 'POST') {
      if (!checkAdmin(req)) return res.status(401).json({ success: false, error: 'unauthorized' });
      const company = body(req).company;
      if (company) await sql`UPDATE company_spending SET spent=0 WHERE company=${company}`;
      return res.status(200).json({ success: true });
    }
    res.status(405).json({ success: false, error: 'method not allowed' });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
