<?php
use App\Core\View;

title('টিকেট');

$tabs = [
    'open'       => 'চলমান',
    'mine'       => 'আমার',
    'unassigned' => 'আনঅ্যাসাইনড',
    'overdue'    => 'সময় পার',
    'pending'    => 'অপেক্ষায়',
    'closed'     => 'বন্ধ',
];

/** বর্তমান ফিল্টার ধরে রেখে শুধু একটি প্যারামিটার বদলায়। */
$link = static function (array $changes) use ($queryString): string {
    return '?' . http_build_query(array_filter(array_merge($queryString, $changes), static fn ($v) => $v !== null && $v !== ''));
};
?>

<div class="queue-toolbar">
    <div class="queue-tabs">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?= e($link(['filter' => $key, 'page' => null])) ?>"
               class="queue-tab <?= $filter === $key ? 'active' : '' ?>">
                <?= e($label) ?>
                <?php if (($counts[$key] ?? 0) > 0): ?>
                    <span class="count"><?= (int) $counts[$key] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <a href="<?= e(url('/agent/tickets/new')) ?>" class="btn-hd btn-primary-hd btn-sm-hd" style="width:auto">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        নতুন টিকেট
    </a>
</div>

<form method="get" class="queue-filters">
    <input type="hidden" name="filter" value="<?= e($filter) ?>">
    <input class="input-hd" type="search" name="q" value="<?= e($search) ?>"
           placeholder="নম্বর, বিষয় বা গ্রাহক খুঁজুন…" style="flex:1;min-width:200px">

    <select class="input-hd" name="dept" style="width:auto;min-width:150px">
        <option value="">সব ডিপার্টমেন্ট</option>
        <?php foreach ($departments as $dept): ?>
            <option value="<?= (int) $dept['id'] ?>" <?= $deptId === (int) $dept['id'] ? 'selected' : '' ?>>
                <?= e($dept['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select class="input-hd" name="status" style="width:auto;min-width:140px">
        <option value="">সব স্ট্যাটাস</option>
        <?php foreach ($statuses as $status): ?>
            <option value="<?= (int) $status['id'] ?>" <?= $statusId === (int) $status['id'] ? 'selected' : '' ?>>
                <?= e($status['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn-hd btn-ghost-hd btn-sm-hd" style="width:auto">ফিল্টার</button>
    <?php if ($search !== '' || $deptId > 0 || $statusId > 0): ?>
        <a href="<?= e($link(['q' => null, 'dept' => null, 'status' => null, 'page' => null])) ?>"
           class="muted" style="font-size:13px;align-self:center">পরিষ্কার</a>
    <?php endif; ?>
</form>

<div class="card-hd"><div class="card-hd-body tight">
<?php if ($tickets->isEmpty()): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <h3>কোনো টিকেট নেই</h3>
        <p><?= $search !== '' ? 'এই খোঁজে কিছু মেলেনি — অন্য শব্দ দিয়ে চেষ্টা করুন।' : 'এই তালিকায় এখন কিছু নেই।' ?></p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table-hd">
            <thead>
                <tr>
                    <th style="width:42%">
                        <a href="<?= e($link(['sort' => 'number', 'dir' => $sort === 'number' && $direction === 'asc' ? 'desc' : 'asc'])) ?>" class="th-sort">টিকেট</a>
                    </th>
                    <th>গ্রাহক</th>
                    <th>
                        <a href="<?= e($link(['sort' => 'priority', 'dir' => $sort === 'priority' && $direction === 'desc' ? 'asc' : 'desc'])) ?> " class="th-sort">প্রায়োরিটি</a>
                    </th>
                    <th>স্ট্যাটাস</th>
                    <th>দায়িত্বে</th>
                    <th>
                        <a href="<?= e($link(['sort' => 'due', 'dir' => $sort === 'due' && $direction === 'asc' ? 'desc' : 'asc'])) ?>" class="th-sort">সময়সীমা</a>
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tickets->items as $ticket): ?>
                <?php
                $isLate = $ticket['due_at'] !== null
                    && strtotime((string) $ticket['due_at']) < time()
                    && in_array($ticket['status_state'], ['open', 'paused'], true);
                ?>
                <tr class="<?= $isLate ? 'row-late' : '' ?>">
                    <td>
                        <a href="<?= e(url('/agent/tickets/' . $ticket['id'])) ?>" style="font-weight:600">
                            <?= e($ticket['subject']) ?>
                        </a>
                        <div class="mono faint" style="font-size:11.5px">
                            #<?= e($ticket['number']) ?> · <?= e($ticket['dept_name']) ?>
                            <?php if ((int) $ticket['is_answered'] === 0 && $ticket['status_state'] === 'open'): ?>
                                · <span style="color:var(--warning);font-weight:600">উত্তর বাকি</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:13.5px"><?= e($ticket['user_name']) ?></div>
                        <div class="faint" style="font-size:11.5px"><?= e($ticket['user_email']) ?></div>
                    </td>
                    <td><?= View::partial('partials/ticket_badges', ['kind' => 'priority', 'name' => $ticket['priority_name'], 'color' => $ticket['priority_color']]) ?></td>
                    <td><?= View::partial('partials/ticket_badges', ['name' => $ticket['status_name'], 'color' => $ticket['status_color']]) ?></td>
                    <td>
                        <?php if ($ticket['agent_name'] !== null): ?>
                            <span style="font-size:13.5px"><?= e($ticket['agent_name']) ?></span>
                        <?php else: ?>
                            <span class="faint" style="font-size:13px">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="<?= $isLate ? 'color:var(--danger);font-weight:600' : 'color:var(--text-muted)' ?>">
                        <?= e(time_ago($ticket['due_at'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div></div>

<?php if ($tickets->lastPage() > 1): ?>
    <div style="display:flex;align-items:center;gap:8px;margin-top:20px;flex-wrap:wrap">
        <span class="muted" style="font-size:13px">
            <?= (int) $tickets->from() ?>–<?= (int) $tickets->to() ?> / মোট <?= (int) $tickets->total ?>
        </span>
        <div style="display:flex;gap:6px;margin-left:auto;flex-wrap:wrap">
            <?php foreach ($tickets->window() as $page): ?>
                <?php if ($page === '…'): ?>
                    <span class="faint" style="padding:7px 8px">…</span>
                <?php else: ?>
                    <a href="<?= e($link(['page' => $page])) ?>"
                       class="btn-hd <?= $page === $tickets->page ? 'btn-primary-hd' : 'btn-ghost-hd' ?> btn-sm-hd"
                       style="width:auto;min-width:36px"><?= (int) $page ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
