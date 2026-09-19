<?php
use App\Core\View;

title('হেল্প টপিক');
?>

<?= View::partial('partials/admin_header', [
    'heading'    => 'হেল্প টপিক',
    'subtitle'   => 'গ্রাহকের ফর্মের "কী নিয়ে সমস্যা" তালিকা। এখানেই ঠিক হয় নতুন টিকেট কোথায় যাবে।',
    'actionUrl'  => '/admin/topics/new',
    'actionText' => 'নতুন টপিক',
]) ?>

<div class="card-hd"><div class="card-hd-body tight">
<?php if ($topics === []): ?>
    <div class="empty-state"><h3>কোনো টপিক নেই</h3><p>টপিক না থাকলে সব টিকেট ডিফল্ট ডিপার্টমেন্টে যাবে।</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table-hd">
            <thead><tr><th>টপিক</th><th>ডিপার্টমেন্ট</th><th>প্রায়োরিটি</th><th>SLA</th><th>অটো-অ্যাসাইন</th><th>টিকেট</th><th>অবস্থা</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($topics as $topic): ?>
                <?php $id = (int) $topic['id']; ?>
                <tr>
                    <td style="font-weight:600;font-size:13.5px"><?= e($topic['name']) ?></td>
                    <td class="muted"><?= e($topic['dept_name'] ?? 'ডিফল্ট') ?></td>
                    <td>
                        <?php if ($topic['priority_name'] !== null): ?>
                            <?= View::partial('partials/ticket_badges', ['kind' => 'priority', 'name' => $topic['priority_name'], 'color' => $topic['priority_color']]) ?>
                        <?php else: ?><span class="faint">—</span><?php endif; ?>
                    </td>
                    <td class="muted"><?= e($topic['sla_name'] ?? '—') ?></td>
                    <td class="muted"><?= e($topic['agent_name'] ?? $topic['team_name'] ?? '—') ?></td>
                    <td class="mono"><?= (int) ($ticketCounts[$id] ?? 0) ?></td>
                    <td>
                        <span class="pill <?= (int) $topic['is_active'] === 1 ? 'pill-on' : 'pill-off' ?>">
                            <?= (int) $topic['is_active'] === 1 ? 'সক্রিয়' : 'নিষ্ক্রিয়' ?>
                        </span>
                        <?php if ((int) $topic['is_public'] === 0): ?><span class="pill pill-off">শুধু এজেন্ট</span><?php endif; ?>
                    </td>
                    <td>
                        <div class="row-actions">
                            <a href="<?= e(url('/admin/topics/' . $id . '/edit')) ?>" style="font-size:13px">সম্পাদনা</a>
                            <form method="post" action="<?= e(url('/admin/topics/' . $id . '/delete')) ?>"
                                  data-confirm="<?= e($topic['name']) ?> মুছে ফেলতে চান?">
                                <?= csrf_field() ?>
                                <button type="submit" class="link-danger">মুছুন</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div></div>
