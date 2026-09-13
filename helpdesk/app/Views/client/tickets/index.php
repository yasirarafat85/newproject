<?php
use App\Core\View;

title('আমার টিকেট');
?>

<div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;flex-wrap:wrap">
    <h1 style="font-size:24px;margin:0">আমার টিকেট</h1>
    <a href="<?= e(url('/tickets/new')) ?>" class="btn-hd btn-primary-hd btn-sm-hd" style="width:auto;margin-left:auto">
        নতুন টিকেট
    </a>
</div>

<?php if ($tickets->isEmpty()): ?>
    <div class="card-hd"><div class="card-hd-body">
        <div class="empty-state">
            <svg viewBox="0 0 24 24"><path d="M4 4h16v5a3 3 0 000 6v5H4v-5a3 3 0 000-6V4z"/></svg>
            <h3>এখনো কোনো টিকেট নেই</h3>
            <p>সাহায্য দরকার হলে একটি টিকেট খুলুন — আমরা দ্রুত উত্তর দেব।</p>
            <a href="<?= e(url('/tickets/new')) ?>" class="btn-hd btn-primary-hd" style="width:auto;display:inline-flex">নতুন টিকেট খুলুন</a>
        </div>
    </div></div>
<?php else: ?>
    <div class="card-hd"><div class="card-hd-body tight">
        <div class="table-wrap">
            <table class="table-hd">
                <thead>
                    <tr><th>বিষয়</th><th>স্ট্যাটাস</th><th>সর্বশেষ</th></tr>
                </thead>
                <tbody>
                <?php foreach ($tickets->items as $ticket): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/tickets/' . $ticket['number'])) ?>" style="font-weight:600">
                                <?= e($ticket['subject']) ?>
                            </a>
                            <div class="mono faint" style="font-size:11.5px">
                                #<?= e($ticket['number']) ?> · <?= e($ticket['dept_name']) ?>
                            </div>
                        </td>
                        <td><?= View::partial('partials/ticket_badges', ['name' => $ticket['status_name'], 'color' => $ticket['status_color']]) ?></td>
                        <td class="muted"><?= e(time_ago($ticket['updated_at'] ?? $ticket['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>

    <?php if ($tickets->lastPage() > 1): ?>
        <div style="display:flex;gap:6px;justify-content:center;margin-top:24px;flex-wrap:wrap">
            <?php foreach ($tickets->window() as $page): ?>
                <?php if ($page === '…'): ?>
                    <span class="faint" style="padding:7px 10px">…</span>
                <?php else: ?>
                    <a href="?page=<?= (int) $page ?>"
                       class="btn-hd <?= $page === $tickets->page ? 'btn-primary-hd' : 'btn-ghost-hd' ?> btn-sm-hd"
                       style="width:auto;min-width:38px"><?= (int) $page ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
