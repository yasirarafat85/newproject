<?php
use App\Core\View;

title('অ্যাক্টিভিটি লগ');

/** লগ সারির অভিনেতার নাম — একবারেই আনা তালিকা থেকে। */
$actorName = static function (array $row) use ($actorNames): string {
    if ($row['actor_type'] === 'system' || $row['actor_id'] === null) {
        return 'সিস্টেম';
    }

    $names = $actorNames[$row['actor_type']] ?? [];

    return (string) ($names[(int) $row['actor_id']] ?? ('#' . $row['actor_id']));
};
?>

<?= View::partial('partials/admin_header', [
    'heading'  => 'অ্যাক্টিভিটি লগ',
    'subtitle' => 'কে, কী, কখন ও কোন IP থেকে করেছেন। এখান থেকে কিছু মোছা বা বদলানো যায় না।',
]) ?>

<form method="get" class="queue-filters">
    <input class="input-hd" type="search" name="q" value="<?= e($search) ?>" placeholder="বিবরণে খুঁজুন…" style="flex:1;min-width:180px">
    <select class="input-hd" name="action" style="width:auto;min-width:170px">
        <option value="">সব কাজ</option>
        <?php foreach ($actions as $item): ?>
            <option value="<?= e($item) ?>" <?= $action === $item ? 'selected' : '' ?>><?= e($item) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="input-hd" name="actor" style="width:auto;min-width:140px">
        <option value="">সবাই</option>
        <option value="agent"  <?= $actorType === 'agent'  ? 'selected' : '' ?>>এজেন্ট</option>
        <option value="user"   <?= $actorType === 'user'   ? 'selected' : '' ?>>গ্রাহক</option>
        <option value="system" <?= $actorType === 'system' ? 'selected' : '' ?>>সিস্টেম</option>
    </select>
    <button type="submit" class="btn-hd btn-ghost-hd btn-sm-hd" style="width:auto">ফিল্টার</button>
</form>

<div class="card-hd"><div class="card-hd-body tight">
<?php if ($logs->isEmpty()): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <h3>কোনো লগ নেই</h3><p>এই ফিল্টারে কিছু পাওয়া যায়নি।</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table-hd">
            <thead><tr><th>কখন</th><th>কাজ</th><th>বিবরণ</th><th>কে</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs->items as $row): ?>
                <tr>
                    <td style="white-space:nowrap">
                        <div style="font-size:13px"><?= e(format_date($row['created_at'], 'd M Y')) ?></div>
                        <div class="faint" style="font-size:11.5px"><?= e(format_date($row['created_at'], 'h:i A')) ?></div>
                    </td>
                    <td class="mono" style="font-size:12px"><?= e($row['action']) ?></td>
                    <td><?= e($row['description'] ?? '—') ?></td>
                    <td>
                        <div style="font-size:13px"><?= e($actorName($row)) ?></div>
                        <div class="faint" style="font-size:11.5px"><?= e(match ($row['actor_type']) {
                            'agent' => 'এজেন্ট', 'user' => 'গ্রাহক', default => 'স্বয়ংক্রিয়',
                        }) ?></div>
                    </td>
                    <td class="mono faint" style="font-size:11.5px"><?= e($row['ip_address'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div></div>

<?php if ($logs->lastPage() > 1): ?>
    <div style="display:flex;align-items:center;gap:8px;margin-top:20px;flex-wrap:wrap">
        <span class="muted" style="font-size:13px"><?= (int) $logs->from() ?>–<?= (int) $logs->to() ?> / মোট <?= (int) $logs->total ?></span>
        <div style="display:flex;gap:6px;margin-left:auto;flex-wrap:wrap">
            <?php foreach ($logs->window() as $page): ?>
                <?php if ($page === '…'): ?>
                    <span class="faint" style="padding:7px 8px">…</span>
                <?php else: ?>
                    <a href="?<?= e(http_build_query(array_filter(['q' => $search, 'action' => $action, 'actor' => $actorType, 'page' => $page]))) ?>"
                       class="btn-hd <?= $page === $logs->page ? 'btn-primary-hd' : 'btn-ghost-hd' ?> btn-sm-hd"
                       style="width:auto;min-width:36px"><?= (int) $page ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
