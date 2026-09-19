<?php
use App\Core\View;

title('ডিপার্টমেন্ট');
$strategies = ['manual' => 'ম্যানুয়াল', 'round_robin' => 'ঘুরিয়ে', 'least_load' => 'কম বোঝা যার'];
?>

<?= View::partial('partials/admin_header', [
    'heading'    => 'ডিপার্টমেন্ট',
    'subtitle'   => 'টিকেট কোন দলের কাছে যাবে তা ঠিক করে। প্রতিটির নিজস্ব SLA ও অ্যাসাইনমেন্ট নিয়ম থাকতে পারে।',
    'actionUrl'  => '/admin/departments/new',
    'actionText' => 'নতুন ডিপার্টমেন্ট',
]) ?>

<div class="card-hd"><div class="card-hd-body tight">
    <div class="table-wrap">
        <table class="table-hd">
            <thead><tr><th>নাম</th><th>ম্যানেজার</th><th>SLA</th><th>অ্যাসাইনমেন্ট</th><th>টিকেট</th><th>এজেন্ট</th><th>অবস্থা</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($departments as $dept): ?>
                <?php $id = (int) $dept['id']; ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13.5px">
                            <?php if ($dept['parent_name'] !== null): ?>
                                <span class="faint" style="font-weight:400"><?= e($dept['parent_name']) ?> ›</span>
                            <?php endif; ?>
                            <?= e($dept['name']) ?>
                            <?php if ((int) $dept['is_default'] === 1): ?><span class="pill pill-key">ডিফল্ট</span><?php endif; ?>
                        </div>
                        <?php if ((int) $dept['is_public'] === 0): ?>
                            <div class="faint" style="font-size:11.5px">গ্রাহকের ফর্মে দেখা যায় না</div>
                        <?php endif; ?>
                    </td>
                    <td class="muted"><?= e($dept['manager_name'] ?? '—') ?></td>
                    <td class="muted"><?= e($dept['sla_name'] ?? '—') ?></td>
                    <td class="muted"><?= e($strategies[$dept['assignment_strategy']] ?? $dept['assignment_strategy']) ?></td>
                    <td class="mono"><?= (int) ($ticketCounts[$id] ?? 0) ?></td>
                    <td class="mono"><?= (int) ($agentCounts[$id] ?? 0) ?></td>
                    <td>
                        <?php if ((int) $dept['is_active'] === 1): ?>
                            <span class="pill pill-on">সক্রিয়</span>
                        <?php else: ?>
                            <span class="pill pill-off">নিষ্ক্রিয়</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-actions">
                            <a href="<?= e(url('/admin/departments/' . $id . '/edit')) ?>" style="font-size:13px">সম্পাদনা</a>
                            <?php if ((int) $dept['is_default'] === 0 && (int) ($ticketCounts[$id] ?? 0) === 0): ?>
                                <form method="post" action="<?= e(url('/admin/departments/' . $id . '/delete')) ?>"
                                      data-confirm="<?= e($dept['name']) ?> মুছে ফেলতে চান?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="link-danger">মুছুন</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div></div>
