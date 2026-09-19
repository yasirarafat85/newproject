<?php
use App\Core\View;

$isEdit = $department !== null;
title($isEdit ? 'ডিপার্টমেন্ট সম্পাদনা' : 'নতুন ডিপার্টমেন্ট');

$value = static function (string $key, mixed $fallback = '') use ($department) {
    return old($key, $department[$key] ?? $fallback);
};
?>

<?= View::partial('partials/admin_header', [
    'heading'  => $isEdit ? e($department['name']) . ' — সম্পাদনা' : 'নতুন ডিপার্টমেন্ট',
    'subtitle' => 'নতুন টিকেট এই ডিপার্টমেন্টে এলে কী নিয়মে চলবে।',
]) ?>

<form method="post" action="<?= e(url($isEdit ? '/admin/departments/' . $department['id'] : '/admin/departments')) ?>"
      data-once style="max-width:820px">
    <?= csrf_field() ?>

    <div class="card-hd">
        <div class="card-hd-head"><h2>মৌলিক তথ্য</h2></div>
        <div class="card-hd-body">
            <div class="field">
                <label for="name">নাম <span class="required">*</span></label>
                <input class="input-hd" type="text" id="name" name="name" value="<?= e($value('name')) ?>" required>
                <?php if ($m = error_for('name')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
                <div class="field">
                    <label for="parent_id">প্যারেন্ট ডিপার্টমেন্ট</label>
                    <select class="input-hd" id="parent_id" name="parent_id">
                        <option value="">— নেই —</option>
                        <?php foreach ($parents as $parent): ?>
                            <option value="<?= (int) $parent['id'] ?>" <?= (int) $value('parent_id') === (int) $parent['id'] ? 'selected' : '' ?>>
                                <?= e($parent['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="manager_id">ম্যানেজার</label>
                    <select class="input-hd" id="manager_id" name="manager_id">
                        <option value="">— নেই —</option>
                        <?php foreach ($managers as $manager): ?>
                            <option value="<?= (int) $manager['id'] ?>" <?= (int) $value('manager_id') === (int) $manager['id'] ? 'selected' : '' ?>>
                                <?= e($manager['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">আনঅ্যাসাইনড টিকেটের খবর ইনি পাবেন</div>
                </div>
                <div class="field">
                    <label for="sla_id">SLA প্ল্যান</label>
                    <select class="input-hd" id="sla_id" name="sla_id">
                        <option value="">— ডিফল্ট —</option>
                        <?php foreach ($slaPlans as $sla): ?>
                            <option value="<?= (int) $sla['id'] ?>" <?= (int) $value('sla_id') === (int) $sla['id'] ? 'selected' : '' ?>>
                                <?= e($sla['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="sort_order">ক্রম</label>
                    <input class="input-hd" type="number" id="sort_order" name="sort_order" value="<?= e($value('sort_order', 0)) ?>" min="0">
                </div>
            </div>

            <div class="field" style="margin-bottom:0">
                <label for="signature">ডিপার্টমেন্ট সিগনেচার</label>
                <textarea class="input-hd" id="signature" name="signature" rows="3"><?= e($value('signature')) ?></textarea>
                <div class="hint">এই ডিপার্টমেন্ট থেকে যাওয়া ইমেইলের নিচে বসবে</div>
            </div>
        </div>
    </div>

    <div class="card-hd">
        <div class="card-hd-head"><h2>নিয়ম</h2></div>
        <div class="card-hd-body">
            <div class="field">
                <label for="assignment_strategy">অটো-অ্যাসাইনমেন্ট কৌশল</label>
                <select class="input-hd" id="assignment_strategy" name="assignment_strategy">
                    <option value="manual"      <?= $value('assignment_strategy', 'manual') === 'manual' ? 'selected' : '' ?>>ম্যানুয়াল — কেউ claim না করা পর্যন্ত অপেক্ষা</option>
                    <option value="round_robin" <?= $value('assignment_strategy') === 'round_robin' ? 'selected' : '' ?>>ঘুরিয়ে — সবাইকে পালা করে</option>
                    <option value="least_load"  <?= $value('assignment_strategy') === 'least_load' ? 'selected' : '' ?>>কম বোঝা যার — যার খোলা টিকেট সবচেয়ে কম</option>
                </select>
                <div class="hint">অটো-অ্যাসাইনমেন্ট P3-এ কার্যকর হবে</div>
            </div>

            <?= View::partial('partials/toggle', [
                'name' => 'is_public', 'label' => 'গ্রাহকের ফর্মে দেখা যাবে',
                'hint' => 'বন্ধ করলে শুধু এজেন্টরাই এখানে টিকেট পাঠাতে পারবেন',
                'checked' => (bool) $value('is_public', 1),
            ]) ?>
            <?= View::partial('partials/toggle', [
                'name' => 'auto_response', 'label' => 'স্বয়ংক্রিয় প্রাপ্তি-স্বীকার পাঠানো',
                'hint' => 'নতুন টিকেটে গ্রাহক সঙ্গে সঙ্গে একটি ইমেইল পাবেন (P4)',
                'checked' => (bool) $value('auto_response', 1),
            ]) ?>
            <?= View::partial('partials/toggle', [
                'name' => 'is_active', 'label' => 'সক্রিয়',
                'hint' => 'নিষ্ক্রিয় ডিপার্টমেন্টে নতুন টিকেট যাবে না',
                'checked' => (bool) $value('is_active', 1),
            ]) ?>
            <?= View::partial('partials/toggle', [
                'name' => 'is_default', 'label' => 'ডিফল্ট ডিপার্টমেন্ট',
                'hint' => 'কোনো টপিক বা নিয়ম না মিললে টিকেট এখানেই আসবে',
                'checked' => (bool) $value('is_default', 0),
            ]) ?>
        </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:var(--sp-4)">
        <button type="submit" class="btn-hd btn-primary-hd" style="width:auto"><?= $isEdit ? 'সংরক্ষণ করুন' : 'যোগ করুন' ?></button>
        <a href="<?= e(url('/admin/departments')) ?>" class="btn-hd btn-ghost-hd" style="width:auto">বাতিল</a>
    </div>
</form>
