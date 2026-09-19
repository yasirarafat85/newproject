<?php
use App\Core\View;

title('রোল ও পারমিশন');
?>

<?= View::partial('partials/admin_header', [
    'heading'    => 'রোল ও পারমিশন',
    'subtitle'   => 'রোল ঠিক করে দেয় একজন এজেন্ট কী কী করতে পারবেন। সিস্টেম রোল মুছে ফেলা যায় না।',
    'actionUrl'  => '/admin/roles/new',
    'actionText' => 'নতুন রোল',
]) ?>

<div class="card-hd"><div class="card-hd-body tight">
    <div class="table-wrap">
        <table class="table-hd">
            <thead><tr><th>রোল</th><th>পারমিশন</th><th>এজেন্ট</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($roles as $role): ?>
                <?php
                $id = (int) $role['id'];
                $granted = (int) ($permissionCount[$id] ?? 0);
                $agents = (int) ($agentCount[$id] ?? 0);
                $isSystem = (int) $role['is_system'] === 1;
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13.5px">
                            <?= e($role['name']) ?>
                            <?php if ($isSystem): ?><span class="pill pill-off">সিস্টেম</span><?php endif; ?>
                        </div>
                        <?php if (($role['description'] ?? '') !== ''): ?>
                            <div class="faint" style="font-size:12px"><?= e($role['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="min-width:170px">
                        <div style="display:flex;align-items:center;gap:9px">
                            <div style="flex:1;height:6px;background:var(--surface-2);border-radius:999px;overflow:hidden;max-width:110px">
                                <div style="height:100%;width:<?= $totalPermissions > 0 ? (int) round(($granted / $totalPermissions) * 100) : 0 ?>%;
                                            background:var(--primary);border-radius:999px"></div>
                            </div>
                            <span class="mono" style="font-size:12px"><?= $granted ?>/<?= (int) $totalPermissions ?></span>
                        </div>
                    </td>
                    <td class="mono"><?= $agents ?></td>
                    <td>
                        <div class="row-actions">
                            <a href="<?= e(url('/admin/roles/' . $id . '/edit')) ?>" style="font-size:13px">সম্পাদনা</a>
                            <?php if (!$isSystem && $agents === 0): ?>
                                <form method="post" action="<?= e(url('/admin/roles/' . $id . '/delete')) ?>"
                                      data-confirm="<?= e($role['name']) ?> রোলটি মুছে ফেলতে চান?">
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
