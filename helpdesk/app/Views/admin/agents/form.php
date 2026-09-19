<?php
use App\Core\Auth;
use App\Core\View;

$isEdit = $agent !== null;
title($isEdit ? 'এজেন্ট সম্পাদনা' : 'নতুন এজেন্ট');

/** এডিটে পুরনো মান, নতুনে ফাঁকা — ভ্যালিডেশন ব্যর্থ হলে old() জেতে। */
$value = static function (string $key, mixed $fallback = '') use ($agent) {
    return old($key, $agent[$key] ?? $fallback);
};
$isSelf = $isEdit && (int) $agent['id'] === (int) Auth::agentId();
?>

<?= View::partial('partials/admin_header', [
    'heading'  => $isEdit ? e($agent['name']) . ' — সম্পাদনা' : 'নতুন এজেন্ট',
    'subtitle' => $isEdit ? 'ইউজারনেম বদলালে এজেন্টকে জানিয়ে দিন।' : 'নতুন সহকর্মীর অ্যাকাউন্ট তৈরি করুন।',
]) ?>

<form method="post" action="<?= e(url($isEdit ? '/admin/agents/' . $agent['id'] : '/admin/agents')) ?>" data-once style="max-width:980px">
    <?= csrf_field() ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:var(--sp-4);align-items:start">

        <div class="card-hd" style="margin:0">
            <div class="card-hd-head"><h2>পরিচয়</h2></div>
            <div class="card-hd-body">
                <div class="field">
                    <label for="name">পূর্ণ নাম <span class="required">*</span></label>
                    <input class="input-hd" type="text" id="name" name="name" value="<?= e($value('name')) ?>" required>
                    <?php if ($m = error_for('name')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="username">ইউজারনেম <span class="required">*</span></label>
                    <input class="input-hd" type="text" id="username" name="username" value="<?= e($value('username')) ?>" required minlength="3" autocomplete="off">
                    <?php if ($m = error_for('username')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="email">ইমেইল <span class="required">*</span></label>
                    <input class="input-hd" type="email" id="email" name="email" value="<?= e($value('email')) ?>" required>
                    <?php if ($m = error_for('email')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="mobile">মোবাইল</label>
                    <input class="input-hd" type="tel" id="mobile" name="mobile" value="<?= e($value('mobile')) ?>">
                </div>
                <div class="field" style="margin-bottom:0">
                    <label for="signature">ইমেইল সিগনেচার</label>
                    <textarea class="input-hd" id="signature" name="signature" rows="3"><?= e($value('signature')) ?></textarea>
                    <div class="hint">গ্রাহককে পাঠানো উত্তরের নিচে যুক্ত হবে</div>
                </div>
            </div>
        </div>

        <div class="card-hd" style="margin:0">
            <div class="card-hd-head"><h2>পাসওয়ার্ড</h2></div>
            <div class="card-hd-body">
                <?php if ($isEdit): ?>
                    <div class="alert-hd alert-info" style="margin-bottom:16px">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span>ফাঁকা রাখলে আগের পাসওয়ার্ডই থাকবে।</span>
                    </div>
                <?php endif; ?>
                <div class="field">
                    <label for="password">পাসওয়ার্ড <?= $isEdit ? '' : '<span class="required">*</span>' ?></label>
                    <input class="input-hd" type="password" id="password" name="password" <?= $isEdit ? '' : 'required' ?> minlength="8" autocomplete="new-password">
                    <div class="hint">অন্তত ৮ অক্ষর</div>
                    <?php if ($m = error_for('password')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                </div>
                <div class="field" style="margin-bottom:0">
                    <label for="password_confirmation">পাসওয়ার্ড আবার লিখুন</label>
                    <input class="input-hd" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
                </div>
            </div>
        </div>

        <div class="card-hd" style="margin:0">
            <div class="card-hd-head"><h2>অধিকার</h2></div>
            <div class="card-hd-body">
                <div class="field">
                    <label for="role_id">রোল <span class="required">*</span></label>
                    <select class="input-hd" id="role_id" name="role_id" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['id'] ?>" <?= (int) $value('role_id') === (int) $role['id'] ? 'selected' : '' ?>>
                                <?= e($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">রোল ঠিক করে দেয় এই এজেন্ট কী কী করতে পারবেন</div>
                </div>

                <div class="field">
                    <label for="status">অবস্থা</label>
                    <select class="input-hd" id="status" name="status" <?= $isSelf ? 'disabled' : '' ?>>
                        <?php foreach (['active' => 'সক্রিয়', 'locked' => 'লকড', 'disabled' => 'নিষ্ক্রিয়'] as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $value('status', 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isSelf): ?>
                        <input type="hidden" name="status" value="<?= e($value('status', 'active')) ?>">
                        <div class="hint">নিজের অ্যাকাউন্টের অবস্থা নিজে বদলানো যায় না</div>
                    <?php endif; ?>
                </div>

                <?= View::partial('partials/toggle', [
                    'name'    => 'is_admin',
                    'label'   => 'সুপার অ্যাডমিন',
                    'hint'    => $isSelf ? 'নিজের অ্যাডমিন অধিকার নিজে সরানো যায় না' : 'রোল যাই হোক, সব কাজের অনুমতি পাবেন',
                    'checked' => (bool) $value('is_admin', 0),
                ]) ?>

                <?= View::partial('partials/toggle', [
                    'name'    => 'is_available',
                    'label'   => 'অটো-অ্যাসাইনে অন্তর্ভুক্ত',
                    'hint'    => 'ছুটিতে থাকলে বন্ধ রাখুন',
                    'checked' => (bool) $value('is_available', 1),
                ]) ?>

                <div class="field" style="margin:14px 0 0">
                    <label for="max_open_tickets">সর্বোচ্চ খোলা টিকেট</label>
                    <input class="input-hd" type="number" id="max_open_tickets" name="max_open_tickets"
                           value="<?= e($value('max_open_tickets')) ?>" min="0" placeholder="সীমাহীন">
                    <div class="hint">least-load অটো-অ্যাসাইনমেন্টে এই সীমা মানা হবে</div>
                </div>
            </div>
        </div>

        <div class="card-hd" style="margin:0">
            <div class="card-hd-head"><h2>ডিপার্টমেন্ট</h2></div>
            <div class="card-hd-body">
                <div class="field">
                    <label for="primary_dept_id">প্রধান ডিপার্টমেন্ট</label>
                    <select class="input-hd" id="primary_dept_id" name="primary_dept_id">
                        <option value="">— নেই —</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= (int) $dept['id'] ?>" <?= (int) $value('primary_dept_id') === (int) $dept['id'] ? 'selected' : '' ?>>
                                <?= e($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">এটি সবসময় সদস্যপদে যোগ হবে</div>
                </div>

                <label class="small-label">সদস্যপদ</label>
                <div class="check-list">
                    <?php foreach ($departments as $dept): ?>
                        <?php
                        $deptId = (int) $dept['id'];
                        $isMember  = $memberships[$deptId]['member'] ?? false;
                        $isManager = $memberships[$deptId]['manager'] ?? false;
                        ?>
                        <div class="check-row">
                            <input type="checkbox" id="dept_<?= $deptId ?>" name="departments[]" value="<?= $deptId ?>" <?= $isMember ? 'checked' : '' ?>>
                            <label for="dept_<?= $deptId ?>"><?= e($dept['name']) ?></label>
                            <span class="spacer"></span>
                            <input type="checkbox" id="mgr_<?= $deptId ?>" name="managers[]" value="<?= $deptId ?>" <?= $isManager ? 'checked' : '' ?>>
                            <label for="mgr_<?= $deptId ?>" class="sub">ম্যানেজার</label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="hint" style="margin-top:8px">এজেন্ট যে ডিপার্টমেন্টগুলোর টিকেট দেখতে পাবেন</div>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:var(--sp-4)">
        <button type="submit" class="btn-hd btn-primary-hd" style="width:auto">
            <?= $isEdit ? 'পরিবর্তন সংরক্ষণ করুন' : 'এজেন্ট যোগ করুন' ?>
        </button>
        <a href="<?= e(url('/admin/agents')) ?>" class="btn-hd btn-ghost-hd" style="width:auto">বাতিল</a>
    </div>
</form>
