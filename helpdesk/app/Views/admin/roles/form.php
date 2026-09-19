<?php
use App\Core\View;

$isEdit = $role !== null;
title($isEdit ? 'রোল সম্পাদনা' : 'নতুন রোল');

$value = static function (string $key, mixed $fallback = '') use ($role) {
    return old($key, $role[$key] ?? $fallback);
};
?>

<?= View::partial('partials/admin_header', [
    'heading'  => $isEdit ? e($role['name']) . ' — সম্পাদনা' : 'নতুন রোল',
    'subtitle' => 'যে কাজগুলোর অনুমতি দিতে চান সেগুলোতে টিক দিন।',
]) ?>

<?php if ($locked): ?>
    <div class="alert-hd alert-warning">
        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        <span><strong>Super Admin</strong> রোলের পারমিশন সুরক্ষিত — বদলানো যায় না।
              নইলে সিস্টেমে এমন কেউ থাকত না যিনি সব কাজ করতে পারেন। নাম ও বিবরণ বদলানো যাবে।</span>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url($isEdit ? '/admin/roles/' . $role['id'] : '/admin/roles')) ?>"
      data-once style="max-width:980px">
    <?= csrf_field() ?>

    <div class="card-hd">
        <div class="card-hd-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px">
                <div class="field" style="margin:0">
                    <label for="name">রোলের নাম <span class="required">*</span></label>
                    <input class="input-hd" type="text" id="name" name="name" value="<?= e($value('name')) ?>" required>
                    <?php if ($m = error_for('name')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                </div>
                <div class="field" style="margin:0">
                    <label for="description">বিবরণ</label>
                    <input class="input-hd" type="text" id="description" name="description" value="<?= e($value('description')) ?>"
                           placeholder="এই রোলটি কাদের জন্য">
                </div>
            </div>
        </div>
    </div>

    <?php if (!$locked): ?>
    <div class="card-hd">
        <div class="card-hd-head">
            <h2>পারমিশন</h2>
            <div class="perm-toolbar">
                <button type="button" data-perm-all="1">সব বাছুন</button>
                <button type="button" data-perm-all="0">সব বাদ</button>
            </div>
        </div>
        <div class="card-hd-body">
            <?php foreach ($groups as $groupCode => $group): ?>
                <div class="perm-group" data-perm-group="<?= e($groupCode) ?>">
                    <div class="perm-group-head">
                        <h4><?= e($group['label']) ?></h4>
                        <div class="perm-toolbar">
                            <button type="button" data-perm-group-all="<?= e($groupCode) ?>" data-on="1">সব</button>
                            <button type="button" data-perm-group-all="<?= e($groupCode) ?>" data-on="0">কিছু না</button>
                        </div>
                    </div>
                    <div class="perm-list">
                        <?php foreach ($group['items'] as $permission): ?>
                            <?php $code = (string) $permission['code']; ?>
                            <label class="perm-item" for="perm_<?= e(str_replace('.', '_', $code)) ?>">
                                <input type="checkbox" id="perm_<?= e(str_replace('.', '_', $code)) ?>"
                                       name="permissions[]" value="<?= e($code) ?>"
                                       <?= in_array($code, $granted, true) ? 'checked' : '' ?>>
                                <span>
                                    <span class="name"><?= e($permission['label']) ?></span>
                                    <span class="code"><?= e($code) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:12px;margin-top:var(--sp-4)">
        <button type="submit" class="btn-hd btn-primary-hd" style="width:auto"><?= $isEdit ? 'সংরক্ষণ করুন' : 'রোল তৈরি করুন' ?></button>
        <a href="<?= e(url('/admin/roles')) ?>" class="btn-hd btn-ghost-hd" style="width:auto">বাতিল</a>
    </div>
</form>

<script type="module" src="<?= e(asset('js/modules/permissions.js')) ?>"></script>
