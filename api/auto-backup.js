// GET /api/auto-backup — throttled daily snapshot (no Vercel cron needed).
// Called by the frontend on load; creates an "auto" backup only if the newest
// one is older than ~20h, so opening the app naturally keeps a daily copy.
const { sql, ensureSchema, logActivity } = require('./_db');
const { createBackup, pruneAuto } = require('./_backup');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    const last = await sql`SELECT created_at FROM backups WHERE kind='auto' ORDER BY created_at DESC LIMIT 1`;
    if (last.length) {
      const ageMs = Date.now() - new Date(last[0].created_at).getTime();
      if (ageMs < 20 * 3600 * 1000) return res.status(200).json({ success: true, skipped: true });
    }
    const n = await createBackup('auto');
    await pruneAuto(14);
    await logActivity('system', 'backup_auto', n + ' bills');
    res.status(200).json({ success: true, bills: n });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
