<?php
use App\Core\View;

$isEdit = $team !== null;
title($isEdit ? 'টিম সম্পাদনা' : 'নতুন টিম');

$value = static function (string $key, mixed $fallback = '') use ($team) {
    return old($key, $team[$key] ?? $fallback);
};
?>

<?= View::partial('partials/admin_header', [
    'heading'  => $isEdit ? e($team['name']) . ' — সম্পাদনা' : 'নতুন টিম',
    'subtitle' => 'টিম লিড স্বয়ংক্রিয়ভাবে সদস্য হিসেবে যুক্ত হবেন।',
]) ?>

<form method="post" action="<?= e(url($isEdit ? '/admin/teams/' . $team['id'] : '/admin/teams')) ?>"
      data-once style="max-width:720px">
    <?= csrf_field() ?>

    <div class="card-hd">
        <div class="card-hd-body">
            <div class="field">
                <label for="name">টিমের নাম <span class="required">*</span></label>
                <input class="input-hd" type="text" id="name" name="name" value="<?= e($value('name')) ?>" required
                       placeholder="যেমন: Tier 2 কারিগরি">
                <?php if ($m = error_for('name')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="lead_agent_id">টিম লিড</label>
                <select class="input-hd" id="lead_agent_id" name="lead_agent_id">
                    <option value="">— নেই —</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= (int) $agent['id'] ?>" <?= (int) $value('lead_agent_id') === (int) $agent['id'] ? 'selected' : '' ?>>
                            <?= e($agent['name']) ?><?= $agent['dept_name'] === null ? '' : ' — ' . e($agent['dept_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label class="small-label">সদস্য</label>
            <div class="check-list">
                <?php if ($agents === []): ?>
                    <p class="muted" style="font-size:13px;padding:10px 0;margin:0">কোনো সক্রিয় এজেন্ট নেই।</p>
                <?php endif; ?>
                <?php foreach ($agents as $agent): ?>
                    <?php $agentId = (int) $agent['id']; ?>
                    <div class="check-row">
                        <input type="checkbox" id="member_<?= $agentId ?>" name="members[]" value="<?= $agentId ?>"
                               <?= in_array($agentId, $members, true) ? 'checked' : '' ?>>
                        <label for="member_<?= $agentId ?>"><?= e($agent['name']) ?></label>
                        <span class="spacer"></span>
                        <span class="sub"><?= e($agent['dept_name'] ?? '—') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:14px">
                <?= View::partial('partials/toggle', [
                    'name' => 'notify_lead', 'label' => 'নতুন টিকেটে লিডকে জানানো',
                    'checked' => (bool) $value('notify_lead', 1),
                ]) ?>
                <?= View::partial('partials/toggle', [
                    'name' => 'is_active', 'label' => 'সক্রিয়',
                    'hint' => 'নিষ্ক্রিয় টিমে নতুন টিকেট পাঠানো যাবে না',
                    'checked' => (bool) $value('is_active', 1),
                ]) ?>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:var(--sp-4)">
        <button type="submit" class="btn-hd btn-primary-hd" style="width:auto"><?= $isEdit ? 'সংরক্ষণ করুন' : 'টিম তৈরি করুন' ?></button>
        <a href="<?= e(url('/admin/teams')) ?>" class="btn-hd btn-ghost-hd" style="width:auto">বাতিল</a>
    </div>
</form>
