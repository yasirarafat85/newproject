<?php title('এজেন্ট লগইন'); ?>
<div class="auth-head">
    <span class="brand-mark">HD</span>
    <h1>এজেন্ট প্যানেল</h1>
    <p><?= e($companyName ?? 'HelpDesk') ?> সাপোর্ট ডেস্ক</p>
</div>

<form method="post" action="<?= e(url('/agent/login')) ?>" data-once>
    <?= csrf_field() ?>
    <div class="field">
        <label for="login">ইউজারনেম বা ইমেইল</label>
        <input class="input-hd <?= error_for('login') ? 'is-invalid' : '' ?>" type="text" id="login" name="login"
               value="<?= e(old('login')) ?>" required autofocus autocomplete="username">
        <?php if ($message = error_for('login')): ?><span class="field-error"><?= e($message) ?></span><?php endif; ?>
    </div>
    <div class="field">
        <label for="password">পাসওয়ার্ড</label>
        <input class="input-hd <?= error_for('password') ? 'is-invalid' : '' ?>" type="password" id="password" name="password"
               required autocomplete="current-password">
        <?php if ($message = error_for('password')): ?><span class="field-error"><?= e($message) ?></span><?php endif; ?>
    </div>
    <button type="submit" class="btn-hd btn-primary-hd btn-block">
        <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        লগইন করুন
    </button>
</form>

<div class="auth-foot">
    গ্রাহক? <a href="<?= e(url('/login')) ?>">সাপোর্ট পোর্টালে যান</a>
</div>
