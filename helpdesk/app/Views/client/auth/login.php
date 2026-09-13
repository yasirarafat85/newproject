<?php title('লগইন'); ?>
<div class="auth-head">
    <span class="brand-mark">HD</span>
    <h1>স্বাগতম</h1>
    <p>আপনার টিকেট দেখতে লগইন করুন</p>
</div>

<form method="post" action="<?= e(url('/login')) ?>" data-once>
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">ইমেইল</label>
        <input class="input-hd <?= error_for('email') ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
               value="<?= e(old('email')) ?>" required autofocus autocomplete="email">
        <?php if ($message = error_for('email')): ?><span class="field-error"><?= e($message) ?></span><?php endif; ?>
    </div>
    <div class="field">
        <label for="password">পাসওয়ার্ড</label>
        <input class="input-hd" type="password" id="password" name="password" required autocomplete="current-password">
        <?php if ($message = error_for('password')): ?><span class="field-error"><?= e($message) ?></span><?php endif; ?>
    </div>
    <button type="submit" class="btn-hd btn-primary-hd btn-block">লগইন করুন</button>
</form>

<div class="auth-foot">
    <?php if (setting('allow_registration', '1')): ?>
        অ্যাকাউন্ট নেই? <a href="<?= e(url('/register')) ?>">নতুন অ্যাকাউন্ট খুলুন</a><br>
    <?php endif; ?>
    <a href="<?= e(url('/tickets/check')) ?>">অ্যাকাউন্ট ছাড়াই টিকেট খুঁজুন</a>
</div>
