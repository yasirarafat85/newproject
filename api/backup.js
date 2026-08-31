// GET  /api/backup                 -> list backups (admin)
// GET  /api/backup?download=<id>    -> full JSON of one backup (admin)
// POST /api/backup {action:'create'}      -> manual backup now (admin)
// POST /api/backup {action:'restore', id} -> restore from a backup (admin, destructive)
const { sql, ensureSchema, authAdmin, body, logActivity } = require('./_db');
const { createBackup } = require('./_backup');

module.exports = async (req, res) => {
  try {
    await ensureSchema();
    const who = await authAdmin(req);
    if (!who) return res.status(401).json({ success: false, error: 'unauthorized' });

    if (req.method === 'GET') {
      const dl = req.query.download;
      if (dl) {
        const rows = await sql`SELECT data, created_at FROM backups WHERE id=${dl}`;
        if (!rows.length) return res.status(404).json({ success: false, error: 'not found' });
        return res.status(200).json({ success: true, created_at: rows[0].created_at, data: rows[0].data });
      }
      const rows = await sql`
        SELECT id, kind, created_at,
               COALESCE(jsonb_array_length(data->'bills'),0) AS bills
        FROM backups ORDER BY created_at DESC LIMIT 60`;
      return res.status(200).json({ success: true, data: rows });
    }

    if (req.method === 'POST') {
      const b = body(req);
      if (b.action === 'create') {
        const n = await createBackup('manual');
        await logActivity(who, 'backup_create', 'manual, ' + n + ' bills');
        return res.status(200).json({ success: true, bills: n });
      }
      if (b.action === 'restore') {
        const rows = await sql`SELECT data FROM backups WHERE id=${b.id}`;
        if (!rows.length) return res.status(404).json({ success: false, error: 'backup not found' });
        const data = rows[0].data || {};
        // safety snapshot before overwriting
        await createBackup('pre-restore');
        await sql`DELETE FROM bills`;
        const bills = Array.isArray(data.bills) ? data.bills : [];
        const CHUNK = 500;
        for (let i = 0; i < bills.length; i += CHUNK) {
          const slice = bills.slice(i, i + CHUNK);
          const q = slice.map(r => sql`
            INSERT INTO bills (company, period, sl_no, mobile, name, dept, bill, status)
            VALUES (${r.company}, ${r.period}, ${r.sl_no}, ${r.mobile}, ${r.name}, ${r.dept}, ${r.bill}, ${r.status})`);
          if (q.length) await sql.transaction(q);
        }
        for (const c of (data.config || [])) {
          await sql`INSERT INTO config (key, value) VALUES (${c.key}, ${c.value})
                    ON CONFLICT (key) DO UPDATE SET value=${c.value}`;
        }
        await sql`DELETE FROM company_spending`;
        for (const s of (data.company_spending || [])) {
          await sql`INSERT INTO company_spending (company, spent) VALUES (${s.company}, ${s.spent})
                    ON CONFLICT (company) DO UPDATE SET spent=${s.spent}`;
        }
        await logActivity(who, 'backup_restore', 'id ' + b.id + ', ' + bills.length + ' bills');
        return res.status(200).json({ success: true, bills: bills.length });
      }
      return res.status(400).json({ success: false, error: 'unknown action' });
    }

    res.status(405).json({ success: false, error: 'method not allowed' });
  } catch (e) {
    res.status(200).json({ success: false, error: String(e) });
  }
};
