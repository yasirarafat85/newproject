<?php
use App\Core\View;

title('সেটিংস');
?>

<?= View::partial('partials/admin_header', [
    'heading'  => 'সিস্টেম সেটিংস',
    'subtitle' => 'এই মানগুলো সঙ্গে সঙ্গে কার্যকর হয় — কোনো ফাইল বদলাতে হয় না।',
]) ?>

<form method="post" action="<?= e(url('/admin/settings')) ?>" data-once style="max-width:760px">
    <?= csrf_field() ?>

    <?php foreach ($groups as $groupCode => $group): ?>
        <?php if (!isset($grouped[$groupCode])) { continue; } ?>
        <div class="card-hd">
            <div class="card-hd-head">
                <div>
                    <h2><?= e($group['label']) ?></h2>
                    <p class="muted" style="margin:2px 0 0;font-size:12.5px"><?= e($group['hint']) ?></p>
                </div>
            </div>
            <div class="card-hd-body">
                <?php foreach ($grouped[$groupCode] as $setting): ?>
                    <?php
                    $key = (string) $setting['setting_key'];
                    $name = 'settings[' . $key . ']';
                    $current = (string) $setting['setting_value'];
                    ?>

                    <?php if ($setting['value_type'] === 'bool'): ?>
                        <?= View::partial('partials/toggle', [
                            'name'    => $name,
                            'id'      => 'set_' . $key,
                            'label'   => $setting['label'],
                            'hint'    => $setting['hint'],
                            'checked' => $current === '1',
                        ]) ?>
                    <?php else: ?>
                        <div class="field">
                            <label for="set_<?= e($key) ?>"><?= e($setting['label']) ?></label>
                            <input class="input-hd" id="set_<?= e($key) ?>" name="<?= e($name) ?>"
                                   type="<?= $setting['value_type'] === 'int' ? 'number' : 'text' ?>"
                                   <?= $setting['value_type'] === 'int' ? 'min="0"' : '' ?>
                                   value="<?= e($current) ?>">
                            <?php if ($setting['hint'] !== ''): ?>
                                <div class="hint"><?= e($setting['hint']) ?></div>
                            <?php endif; ?>
                            <div class="hint mono" style="font-size:11px;color:var(--text-faint)"><?= e($key) ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div style="display:flex;gap:12px;margin-top:var(--sp-4)">
        <button type="submit" class="btn-hd btn-primary-hd" style="width:auto">সেটিংস সংরক্ষণ করুন</button>
    </div>
</form>
