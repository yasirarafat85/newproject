// GET /api/cron-backup  — called by Vercel Cron (see vercel.json).
// Protected by CRON_SECRET when set (Vercel sends "Authorization: Bearer <CRON_SECRET>").
const { ensureSchema, logActivity } = require('./_db');
const { createBackup, pruneAuto } = require('./_backup');

module.exports = async (req, res) => {
  try {
    const secret = process.env.CRON_SECRET;
    if (secret) {
      const auth = req.headers['authorization'] || '';
      if (auth !== 'Bearer ' + secret) return res.status(401).json({ success: false, error: 'unauthorized' });
    }
    await ensureSchema();
    const n = await createBackup('auto');
    await pruneAuto(14); // keep last 14 daily snapshots
    await logActivity('system', 'backup_auto', n + ' bills');
    res.status(200).json({ success: true, bills: n });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
