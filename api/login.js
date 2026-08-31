// POST /api/login { password } -> { success }
// Verifies admin password server-side (never exposed in the frontend).
const { body } = require('./_db');

module.exports = async (req, res) => {
  const password = String(body(req).password || '');
  const expected = process.env.ADMIN_PASSWORD || 'admin123';
  res.status(200).json({ success: expected.length > 0 && password === expected });
};
