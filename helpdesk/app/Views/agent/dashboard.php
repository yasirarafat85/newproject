<?php
/** এজেন্ট ড্যাশবোর্ড — স্ট্যাট, সাপ্তাহিক ভলিউম, নিজের কিউ, আনঅ্যাসাইনড। */
use App\Core\Auth;

title('ড্যাশবোর্ড');
$maxVolume = max(1, max($weeklyVolume));
?>

<div class="stat-grid">
    <a href="<?= e(url('/agent/tickets?filter=mine')) ?>" class="stat-card">
        <span class="stat-icon brand">
            <svg viewBox="0 0 24 24"><path d="M20 6H4a2 2 0 00-2 2v3a2 2 0 010 4v3a2 2 0 002 2h16a2 2 0 002-2v-3a2 2 0 010-4V8a2 2 0 00-2-2z"/></svg>
        </span>
        <div>
            <div class="stat-value"><?= (int) $stats['mine'] ?></div>
            <div class="stat-label">আমার চলমান টিকেট</div>
        </div>
    </a>

    <a href="<?= e(url('/agent/tickets?filter=unassigned')) ?>" class="stat-card">
        <span class="stat-icon blue">
            <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        </span>
        <div>
            <div class="stat-value"><?= (int) $stats['unassigned'] ?></div>
            <div class="stat-label">আনঅ্যাসাইনড</div>
        </div>
    </a>

    <a href="<?= e(url('/agent/tickets?filter=overdue')) ?>" class="stat-card">
        <span class="stat-icon red">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </span>
        <div>
            <div class="stat-value" style="color:var(--danger)"><?= (int) $stats['overdue'] ?></div>
            <div class="stat-label">সময়সীমা পার</div>
        </div>
    </a>

    <a href="<?= e(url('/agent/tickets?filter=pending')) ?>" class="stat-card">
        <span class="stat-icon orange">
            <svg viewBox="0 0 24 24"><path d="M12 2v6M12 16v6"/><path d="M5 5l3 3M16 16l3 3M5 19l3-3M16 8l3-3"/></svg>
        </span>
        <div>
            <div class="stat-value" style="color:var(--warning)"><?= (int) $stats['pending'] ?></div>
            <div class="stat-label">গ্রাহকের অপেক্ষায়</div>
        </div>
    </a>

    <div class="stat-card">
        <span class="stat-icon green">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </span>
        <div>
            <div class="stat-value" style="color:var(--success)"><?= (int) $stats['resolvedToday'] ?></div>
            <div class="stat-label">আজ সমাধান হয়েছে</div>
        </div>
    </div>
</div>

<div class="card-hd">
    <div class="card-hd-head">
        <h2>গত ৭ দিনের টিকেট</h2>
        <span class="spacer small-label">মোট <?= array_sum($weeklyVolume) ?></span>
    </div>
    <div class="card-hd-body">
        <?php $dayNames = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহ', 'শুক্র', 'শনি']; ?>
        <div style="display:flex;align-items:flex-end;gap:10px;height:150px">
            <?php foreach ($weeklyVolume as $day => $count): ?>
                <?php $heightPercent = (int) round(($count / $maxVolume) * 100); ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;height:100%">
                    <div style="flex:1;width:100%;display:flex;align-items:flex-end">
                        <div title="<?= e($day) ?> — <?= (int) $count ?>টি"
                             style="width:100%;min-height:3px;height:<?= max(3, $heightPercent) ?>%;border-radius:6px 6px 0 0;
                                    background:<?= $count > 0 ? 'linear-gradient(to top,var(--primary-dark),var(--primary))' : 'var(--surface-2)' ?>"></div>
                    </div>
                    <span style="font-size:11.5px;color:var(--text-faint)">
                        <?= e($dayNames[(int) date('w', strtotime($day))]) ?>
                    </span>
                    <span class="mono" style="font-size:12px;font-weight:600"><?= (int) $count ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card-hd">
    <div class="card-hd-head">
        <h2>আমার কিউ</h2>
        <a href="<?= e(url('/agent/tickets?filter=mine')) ?>" class="spacer" style="font-size:13.5px">সব দেখুন →</a>
    </div>
    <div class="card-hd-body tight">
        <?php if ($myTickets === []): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                <h3>আপনার কিউ ফাঁকা</h3>
                <p>এই মুহূর্তে আপনার নামে কোনো চলমান টিকেট নেই।</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-hd">
                    <thead>
                        <tr><th>টিকেট</th><th>গ্রাহক</th><th>প্রায়োরিটি</th><th>স্ট্যাটাস</th><th>সময়সীমা</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($myTickets as $ticket): ?>
                        <?php $isLate = $ticket['due_at'] !== null && strtotime((string) $ticket['due_at']) < time(); ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/agent/tickets/' . $ticket['id'])) ?>" style="font-weight:600">
                                    <?= e($ticket['subject']) ?>
                                </a>
                                <div class="mono faint" style="font-size:11.5px">#<?= e($ticket['number']) ?></div>
                            </td>
                            <td><?= e($ticket['user_name']) ?></td>
                            <td>
                                <span class="badge-hd" style="background:<?= e($ticket['priority_color']) ?>1f;color:<?= e($ticket['priority_color']) ?>">
                                    <span class="dot"></span><?= e($ticket['priority_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-hd" style="background:<?= e($ticket['status_color']) ?>1f;color:<?= e($ticket['status_color']) ?>">
                                    <?= e($ticket['status_name']) ?>
                                </span>
                            </td>
                            <td style="<?= $isLate ? 'color:var(--danger);font-weight:600' : '' ?>">
                                <?= e(time_ago($ticket['due_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (Auth::hasPermission('ticket.assign') || Auth::isAdmin()): ?>
<div class="card-hd">
    <div class="card-hd-head">
        <h2>আনঅ্যাসাইনড টিকেট</h2>
        <a href="<?= e(url('/agent/tickets?filter=unassigned')) ?>" class="spacer" style="font-size:13.5px">সব দেখুন →</a>
    </div>
    <div class="card-hd-body tight">
        <?php if ($unassigned === []): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                <h3>সব টিকেট বণ্টন হয়ে গেছে</h3>
                <p>অপেক্ষমাণ কোনো নতুন টিকেট নেই।</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-hd">
                    <thead>
                        <tr><th>টিকেট</th><th>গ্রাহক</th><th>ডিপার্টমেন্ট</th><th>প্রায়োরিটি</th><th>কখন এসেছে</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($unassigned as $ticket): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/agent/tickets/' . $ticket['id'])) ?>" style="font-weight:600">
                                    <?= e($ticket['subject']) ?>
                                </a>
                                <div class="mono faint" style="font-size:11.5px">#<?= e($ticket['number']) ?></div>
                            </td>
                            <td><?= e($ticket['user_name']) ?></td>
                            <td><?= e($ticket['dept_name']) ?></td>
                            <td>
                                <span class="badge-hd" style="background:<?= e($ticket['priority_color']) ?>1f;color:<?= e($ticket['priority_color']) ?>">
                                    <span class="dot"></span><?= e($ticket['priority_name']) ?>
                                </span>
                            </td>
                            <td class="muted"><?= e(time_ago($ticket['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
