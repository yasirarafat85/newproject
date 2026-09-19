<?php
use App\Core\View;

title('টিম');
?>

<?= View::partial('partials/admin_header', [
    'heading'    => 'টিম',
    'subtitle'   => 'শিফট বা দক্ষতাভিত্তিক দল। টিমের কিউতে টিকেট গেলে যে আগে নেবেন তিনিই পাবেন।',
    'actionUrl'  => '/admin/teams/new',
    'actionText' => 'নতুন টিম',
]) ?>

<div class="card-hd"><div class="card-hd-body tight">
<?php if ($teams === []): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <h3>কোনো টিম নেই</h3>
        <p>একাধিক এজেন্টকে এক কিউতে রাখতে টিম বানান।</p>
        <a href="<?= e(url('/admin/teams/new')) ?>" class="btn-hd btn-primary-hd" style="width:auto;display:inline-flex">প্রথম টিম বানান</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table-hd">
            <thead><tr><th>নাম</th><th>লিড</th><th>সদস্য</th><th>চলমান টিকেট</th><th>অবস্থা</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($teams as $team): ?>
                <?php $id = (int) $team['id']; ?>
                <tr>
                    <td style="font-weight:600;font-size:13.5px"><?= e($team['name']) ?></td>
                    <td class="muted"><?= e($team['lead_name'] ?? '—') ?></td>
                    <td class="mono"><?= (int) ($memberCounts[$id] ?? 0) ?></td>
                    <td class="mono"><?= (int) ($ticketCounts[$id] ?? 0) ?></td>
                    <td>
                        <span class="pill <?= (int) $team['is_active'] === 1 ? 'pill-on' : 'pill-off' ?>">
                            <?= (int) $team['is_active'] === 1 ? 'সক্রিয়' : 'নিষ্ক্রিয়' ?>
                        </span>
                    </td>
                    <td>
                        <div class="row-actions">
                            <a href="<?= e(url('/admin/teams/' . $id . '/edit')) ?>" style="font-size:13px">সম্পাদনা</a>
                            <form method="post" action="<?= e(url('/admin/teams/' . $id . '/delete')) ?>"
                                  data-confirm="<?= e($team['name']) ?> মুছে ফেলতে চান? এই টিমের টিকেটগুলো আনঅ্যাসাইনড হয়ে যাবে।">
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
