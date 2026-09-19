<?php
use App\Core\View;

title('অ্যাডমিন');
$maxDept = max(1, max(array_map(static fn ($r) => (int) $r['total'], $byDepartment ?: [['total' => 0]])));
?>

<div class="stat-grid">
    <a href="<?= e(url('/admin/agents')) ?>" class="stat-card">
        <span class="stat-icon brand"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/></svg></span>
        <div><div class="stat-value"><?= (int) $counts['agents'] ?></div><div class="stat-label">সক্রিয় এজেন্ট</div></div>
    </a>
    <a href="<?= e(url('/admin/departments')) ?>" class="stat-card">
        <span class="stat-icon blue"><svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg></span>
        <div><div class="stat-value"><?= (int) $counts['departments'] ?></div><div class="stat-label">ডিপার্টমেন্ট</div></div>
    </a>
    <a href="<?= e(url('/admin/teams')) ?>" class="stat-card">
        <span class="stat-icon green"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87"/></svg></span>
        <div><div class="stat-value"><?= (int) $counts['teams'] ?></div><div class="stat-label">টিম</div></div>
    </a>
    <a href="<?= e(url('/admin/roles')) ?>" class="stat-card">
        <span class="stat-icon orange"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></span>
        <div><div class="stat-value"><?= (int) $counts['roles'] ?></div><div class="stat-label">রোল</div></div>
    </a>
    <div class="stat-card">
        <span class="stat-icon blue"><svg viewBox="0 0 24 24"><path d="M20 6H4a2 2 0 00-2 2v3a2 2 0 010 4v3a2 2 0 002 2h16a2 2 0 002-2v-3a2 2 0 010-4V8a2 2 0 00-2-2z"/></svg></span>
        <div><div class="stat-value"><?= (int) $counts['tickets'] ?></div><div class="stat-label">মোট টিকেট</div></div>
    </div>
    <div class="stat-card">
        <span class="stat-icon green"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></span>
        <div><div class="stat-value"><?= (int) $counts['users'] ?></div><div class="stat-label">গ্রাহক</div></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:var(--sp-4)">
    <div class="card-hd" style="margin:0">
        <div class="card-hd-head"><h2>সিস্টেমের অবস্থা</h2></div>
        <div class="card-hd-body">
            <?php foreach ($health as $check): ?>
                <div class="health-row">
                    <span class="mark <?= $check['ok'] ? 'ok' : 'no' ?>"><?= $check['ok'] ? '✓' : '!' ?></span>
                    <span style="font-size:14px"><?= e($check['label']) ?></span>
                    <?php if (!$check['ok']): ?>
                        <span class="hint"><?= e($check['hint']) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card-hd" style="margin:0">
        <div class="card-hd-head"><h2>ডিপার্টমেন্ট অনুযায়ী টিকেট</h2></div>
        <div class="card-hd-body">
            <?php if ($byDepartment === []): ?>
                <p class="muted" style="margin:0;font-size:13.5px">এখনো কোনো ডিপার্টমেন্ট নেই।</p>
            <?php else: ?>
                <?php foreach ($byDepartment as $row): ?>
                    <div style="margin-bottom:12px">
                        <div style="display:flex;font-size:13.5px;margin-bottom:5px">
                            <span><?= e($row['name']) ?></span>
                            <b class="mono" style="margin-left:auto"><?= (int) $row['total'] ?></b>
                        </div>
                        <div style="height:6px;background:var(--surface-2);border-radius:999px;overflow:hidden">
                            <div style="height:100%;width:<?= max(2, (int) round(((int) $row['total'] / $maxDept) * 100)) ?>%;
                                        background:linear-gradient(to right,var(--primary-dark),var(--primary));border-radius:999px"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card-hd">
    <div class="card-hd-head">
        <h2>সাম্প্রতিক কার্যক্রম</h2>
        <a href="<?= e(url('/admin/logs')) ?>" class="spacer" style="font-size:13.5px">সব লগ →</a>
    </div>
    <div class="card-hd-body tight">
        <?php if ($recent === []): ?>
            <div class="empty-state"><h3>এখনো কিছু ঘটেনি</h3><p>কাজ শুরু হলে এখানে দেখা যাবে।</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-hd">
                    <thead><tr><th>কাজ</th><th>বিবরণ</th><th>কে</th><th>কখন</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $entry): ?>
                        <tr>
                            <td class="mono" style="font-size:12px"><?= e($entry['action']) ?></td>
                            <td><?= e($entry['description'] ?? '—') ?></td>
                            <td class="muted"><?= e(match ($entry['actor_type']) {
                                'agent' => 'এজেন্ট', 'user' => 'গ্রাহক', default => 'সিস্টেম',
                            }) ?></td>
                            <td class="muted"><?= e(time_ago($entry['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
