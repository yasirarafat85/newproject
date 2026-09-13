<?php title('নতুন অ্যাকাউন্ট'); ?>
<div class="auth-head">
    <span class="brand-mark">HD</span>
    <h1>অ্যাকাউন্ট খুলুন</h1>
    <p>টিকেটের অবস্থা যেকোনো সময় দেখতে পারবেন</p>
</div>

<form method="post" action="<?= e(url('/register')) ?>" data-once>
    <?= csrf_field() ?>
    <div class="field">
        <label for="name">পূর্ণ নাম</label>
        <input class="input-hd <?= error_for('name') ? 'is-invalid' : '' ?>" type="text" id="name" name="name"
               value="<?= e(old('name')) ?>" required autofocus autocomplete="name">
        <?php if ($message = error_for('name')): ?><span class="field-error"><?= e($message) ?></span><?php endif; ?>
    </div>
    <div class="field">
        <label for="email">ইমেইল</label>
        <input class="input-hd <?= error_for('email') ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
               value="<?= e(old('email')) ?>" required autocomplete="email">
        <?php if ($message = error_for('email')): ?><span class="field-error"><?= e($message) ?></span><?php endif; ?>
    </div>
    <div class="field">
        <label for="phone">মোবাইল নম্বর <span class="faint">(ঐচ্ছিক)</span></label>
        <input class="input-hd" type="tel" id="phone" name="phone" value="<?= e(old('phone')) ?>" autocomplete="tel">
        <?php if ($message = error_for('phone')): ?><span class="field-error"><?= e($message) ?></span><?php endif; ?>
    </div>
    <div class="field">
        <label for="password">পাসওয়ার্ড</label>
        <input class="input-hd <?= error_for('password') ? 'is-invalid' : '' ?>" type="password" id="password" name="password"
               required minlength="8" autocomplete="new-password">
        <div class="hint">অন্তত ৮ অক্ষর</div>
        <?php if ($message = error_for('password')): ?><span class="field-error"><?= e($message) ?></span><?php endif; ?>
    </div>
    <div class="field">
        <label for="password_confirmation">পাসওয়ার্ড আবার লিখুন</label>
        <input class="input-hd" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn-hd btn-primary-hd btn-block">অ্যাকাউন্ট তৈরি করুন</button>
</form>

<div class="auth-foot">
    আগে থেকেই অ্যাকাউন্ট আছে? <a href="<?= e(url('/login')) ?>">লগইন করুন</a>
</div>
