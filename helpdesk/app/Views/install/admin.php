<?php title('অ্যাডমিন অ্যাকাউন্ট'); ?>
<div class="install-wrap">
    <h1 style="font-size:26px;margin-bottom:6px">সুপার অ্যাডমিন অ্যাকাউন্ট</h1>
    <p class="muted" style="margin-bottom:32px">এই অ্যাকাউন্ট দিয়েই সব কিছু পরিচালনা করবেন।</p>

    <div class="install-steps">
        <div class="install-step done"><span class="num">✓</span> প্রস্তুতি</div>
        <div class="install-step done"><span class="num">✓</span> ডেটাবেস</div>
        <div class="install-step active"><span class="num">৩</span> অ্যাডমিন</div>
    </div>

    <?= App\Core\View::partial('partials/notice', ['notice' => $notice ?? null]) ?>

    <form method="post" action="<?= e(url('/install/admin')) ?>" data-once>
        <?= csrf_field() ?>
        <div class="card-hd">
            <div class="card-hd-body">
                <div class="field">
                    <label for="company_name">প্রতিষ্ঠানের নাম <span class="required">*</span></label>
                    <input class="input-hd" type="text" id="company_name" name="company_name" value="<?= e(old('company_name')) ?>" required>
                </div>
                <div class="field">
                    <label for="app_url">সাইটের ঠিকানা</label>
                    <input class="input-hd" type="url" id="app_url" name="app_url" value="<?= e(old('app_url', config('app.url'))) ?>">
                    <div class="hint">যেমন <code>http://helpdesk.local</code> — শেষে স্ল্যাশ ছাড়া</div>
                </div>
                <hr style="border:none;border-top:1px solid var(--border);margin:24px 0">
                <div class="field">
                    <label for="name">আপনার পূর্ণ নাম <span class="required">*</span></label>
                    <input class="input-hd" type="text" id="name" name="name" value="<?= e(old('name')) ?>" required>
                </div>
                <div class="field">
                    <label for="username">ইউজারনেম <span class="required">*</span></label>
                    <input class="input-hd" type="text" id="username" name="username" value="<?= e(old('username')) ?>" required minlength="3">
                </div>
                <div class="field">
                    <label for="email">ইমেইল <span class="required">*</span></label>
                    <input class="input-hd" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required>
                </div>
                <div class="field">
                    <label for="password">পাসওয়ার্ড <span class="required">*</span></label>
                    <input class="input-hd" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                    <div class="hint">অন্তত ৮ অক্ষর</div>
                </div>
                <div class="field" style="margin-bottom:0">
                    <label for="password_confirmation">পাসওয়ার্ড আবার লিখুন <span class="required">*</span></label>
                    <input class="input-hd" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>
            </div>
        </div>

        <button type="submit" class="btn-hd btn-success-hd" style="margin-top:24px">
            ইনস্টলেশন সম্পন্ন করুন
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </button>
    </form>
</div>
