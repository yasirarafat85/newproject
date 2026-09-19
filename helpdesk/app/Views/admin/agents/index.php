<?php
use App\Core\Str;
use App\Core\View;

title('এজেন্ট');
?>

<?= View::partial('partials/admin_header', [
    'heading'    => 'এজেন্ট',
    'subtitle'   => 'যাঁরা টিকেটে কাজ করেন। রোল ঠিক করে দেয় কে কী করতে পারবেন।',
    'actionUrl'  => '/admin/agents/new',
    'actionText' => 'নতুন এজেন্ট',
]) ?>

<form method="get" class="queue-filters">
    <input class="input-hd" type="search" name="q" value="<?= e($search) ?>" placeholder="নাম, ইউজারনেম বা ইমেইল…" style="flex:1;min-width:200px">
    <select class="input-hd" name="status" style="width:auto;min-width:150px">
        <option value="">সব অবস্থা</option>
        <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>সক্রিয়</option>
        <option value="locked"   <?= $status === 'locked'   ? 'selected' : '' ?>>লকড</option>
        <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>নিষ্ক্রিয়</option>
    </select>
    <button type="submit" class="btn-hd btn-ghost-hd btn-sm-hd" style="width:auto">ফিল্টার</button>
</form>

<div class="card-hd"><div class="card-hd-body tight">
<?php if ($agents->isEmpty()): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <h3>কোনো এজেন্ট নেই</h3><p>এই খোঁজে কাউকে পাওয়া যায়নি।</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table-hd">
            <thead><tr><th>নাম</th><th>রোল</th><th>ডিপার্টমেন্ট</th><th>অবস্থা</th><th>শেষ লগইন</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($agents->items as $agent): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <span class="avatar sm"><?= e(Str::initials((string) $agent['name'])) ?></span>
                            <div style="min-width:0">
                                <div style="font-weight:600;font-size:13.5px">
                                    <?= e($agent['name']) ?>
                                    <?php if ((int) $agent['is_admin'] === 1): ?>
                                        <span class="pill pill-key">অ্যাডমিন</span>
                                    <?php endif; ?>
                                </div>
                                <div class="faint" style="font-size:11.5px"><?= e($agent['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= e($agent['role_name']) ?></td>
                    <td class="muted"><?= e($agent['dept_name'] ?? '—') ?></td>
                    <td>
                        <?php if ($agent['status'] === 'active'): ?>
                            <span class="pill pill-on">সক্রিয়</span>
                            <?php if ((int) $agent['is_available'] === 0): ?>
                                <span class="pill pill-off">অটো-অ্যাসাইন বন্ধ</span>
                            <?php endif; ?>
                        <?php elseif ($agent['status'] === 'locked'): ?>
                            <span class="pill pill-warn">লকড</span>
                        <?php else: ?>
                            <span class="pill pill-off">নিষ্ক্রিয়</span>
                        <?php endif; ?>
                    </td>
                    <td class="muted"><?= e($agent['last_login_at'] === null ? 'কখনো নয়' : time_ago($agent['last_login_at'])) ?></td>
                    <td>
                        <div class="row-actions">
                            <a href="<?= e(url('/admin/agents/' . $agent['id'] . '/edit')) ?>" style="font-size:13px">সম্পাদনা</a>
                            <form method="post" action="<?= e(url('/admin/agents/' . $agent['id'] . '/delete')) ?>"
                                  data-confirm="<?= e($agent['name']) ?> কে সরিয়ে দিতে চান? টিকেটের ইতিহাসে নামটি থেকে যাবে।">
                                <?= csrf_field() ?>
                                <button type="submit" class="link-danger">সরান</button>
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

<?php if ($agents->lastPage() > 1): ?>
    <div style="display:flex;gap:6px;justify-content:center;margin-top:20px;flex-wrap:wrap">
        <?php foreach ($agents->window() as $page): ?>
            <?php if ($page === '…'): ?>
                <span class="faint" style="padding:7px 8px">…</span>
            <?php else: ?>
                <a href="?<?= e(http_build_query(array_filter(['q' => $search, 'status' => $status, 'page' => $page]))) ?>"
                   class="btn-hd <?= $page === $agents->page ? 'btn-primary-hd' : 'btn-ghost-hd' ?> btn-sm-hd"
                   style="width:auto;min-width:36px"><?= (int) $page ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
