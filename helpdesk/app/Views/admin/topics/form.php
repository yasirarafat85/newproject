<?php
use App\Core\View;

$isEdit = $topic !== null;
title($isEdit ? 'টপিক সম্পাদনা' : 'নতুন টপিক');

$value = static function (string $key, mixed $fallback = '') use ($topic) {
    return old($key, $topic[$key] ?? $fallback);
};

/** ড্রপডাউন — খালি অপশনসহ। */
$select = static function (string $name, string $label, array $rows, mixed $current, string $empty = '— নেই —', string $hint = ''): string {
    $html = '<div class="field"><label for="' . e($name) . '">' . e($label) . '</label>';
    $html .= '<select class="input-hd" id="' . e($name) . '" name="' . e($name) . '">';
    $html .= '<option value="">' . e($empty) . '</option>';

    foreach ($rows as $row) {
        $selected = (int) $current === (int) $row['id'] ? ' selected' : '';
        $html .= '<option value="' . (int) $row['id'] . '"' . $selected . '>' . e($row['name']) . '</option>';
    }

    $html .= '</select>';
    if ($hint !== '') {
        $html .= '<div class="hint">' . e($hint) . '</div>';
    }

    return $html . '</div>';
};
?>

<?= View::partial('partials/admin_header', [
    'heading'  => $isEdit ? e($topic['name']) . ' — সম্পাদনা' : 'নতুন হেল্প টপিক',
    'subtitle' => 'এই টপিক বাছলে টিকেটটি যে নিয়মে চলবে।',
]) ?>

<form method="post" action="<?= e(url($isEdit ? '/admin/topics/' . $topic['id'] : '/admin/topics')) ?>"
      data-once style="max-width:820px">
    <?= csrf_field() ?>

    <div class="card-hd">
        <div class="card-hd-body">
            <div class="field">
                <label for="name">টপিকের নাম <span class="required">*</span></label>
                <input class="input-hd" type="text" id="name" name="name" value="<?= e($value('name')) ?>" required
                       placeholder="যেমন: প্রিন্টার সংক্রান্ত সমস্যা">
                <?php if ($m = error_for('name')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px">
                <?= $select('parent_id', 'প্যারেন্ট টপিক', $parents, $value('parent_id')) ?>
                <?= $select('dept_id', 'ডিপার্টমেন্ট', $departments, $value('dept_id'), '— ডিফল্ট —', 'টিকেট এই ডিপার্টমেন্টে যাবে') ?>
                <?= $select('priority_id', 'প্রায়োরিটি', $priorities, $value('priority_id'), '— ডিফল্ট —') ?>
                <?= $select('sla_id', 'SLA প্ল্যান', $slaPlans, $value('sla_id'), '— ডিপার্টমেন্টেরটি —') ?>
                <?= $select('auto_assign_agent_id', 'সরাসরি এই এজেন্টকে', $agents, $value('auto_assign_agent_id'), '— কেউ নয় —') ?>
                <?= $select('auto_assign_team_id', 'সরাসরি এই টিমকে', $teams, $value('auto_assign_team_id'), '— কেউ নয় —') ?>
                <div class="field">
                    <label for="sort_order">ক্রম</label>
                    <input class="input-hd" type="number" id="sort_order" name="sort_order" value="<?= e($value('sort_order', 0)) ?>" min="0">
                </div>
            </div>

            <div style="margin-top:10px">
                <?= View::partial('partials/toggle', [
                    'name' => 'is_public', 'label' => 'গ্রাহকের ফর্মে দেখা যাবে',
                    'hint' => 'বন্ধ করলে শুধু এজেন্টরাই এই টপিক বাছতে পারবেন',
                    'checked' => (bool) $value('is_public', 1),
                ]) ?>
                <?= View::partial('partials/toggle', [
                    'name' => 'is_active', 'label' => 'সক্রিয়',
                    'checked' => (bool) $value('is_active', 1),
                ]) ?>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:var(--sp-4)">
        <button type="submit" class="btn-hd btn-primary-hd" style="width:auto"><?= $isEdit ? 'সংরক্ষণ করুন' : 'টপিক যোগ করুন' ?></button>
        <a href="<?= e(url('/admin/topics')) ?>" class="btn-hd btn-ghost-hd" style="width:auto">বাতিল</a>
    </div>
</form>
