<?php title('ডেটাবেস সেটআপ'); ?>
<div class="install-wrap">
    <h1 style="font-size:26px;margin-bottom:6px">ডেটাবেস সংযোগ</h1>
    <p class="muted" style="margin-bottom:32px">ডেটাবেসটি আগে থেকে না থাকলে আমরা নিজেই তৈরি করে নেব।</p>

    <div class="install-steps">
        <div class="install-step done"><span class="num">✓</span> প্রস্তুতি</div>
        <div class="install-step active"><span class="num">২</span> ডেটাবেস</div>
        <div class="install-step"><span class="num">৩</span> অ্যাডমিন</div>
    </div>

    <?= App\Core\View::partial('partials/notice', ['notice' => $notice ?? null]) ?>

    <form method="post" action="<?= e(url('/install/database')) ?>" data-once>
        <?= csrf_field() ?>
        <div class="card-hd">
            <div class="card-hd-body">
                <div class="field">
                    <label for="db_host">হোস্ট <span class="required">*</span></label>
                    <input class="input-hd" type="text" id="db_host" name="db_host" value="<?= e(old('db_host', $defaults['host'])) ?>" required>
                    <div class="hint">XAMPP-এ সাধারণত <code>127.0.0.1</code></div>
                </div>
                <div class="field">
                    <label for="db_port">পোর্ট <span class="required">*</span></label>
                    <input class="input-hd" type="number" id="db_port" name="db_port" value="<?= e(old('db_port', $defaults['port'])) ?>" required>
                </div>
                <div class="field">
                    <label for="db_database">ডেটাবেসের নাম <span class="required">*</span></label>
                    <input class="input-hd" type="text" id="db_database" name="db_database" value="<?= e(old('db_database', $defaults['database'])) ?>" required>
                    <div class="hint">শুধু অক্ষর, সংখ্যা ও আন্ডারস্কোর</div>
                </div>
                <div class="field">
                    <label for="db_username">ইউজারনেম <span class="required">*</span></label>
                    <input class="input-hd" type="text" id="db_username" name="db_username" value="<?= e(old('db_username', $defaults['username'])) ?>" required>
                </div>
                <div class="field" style="margin-bottom:0">
                    <label for="db_password">পাসওয়ার্ড</label>
                    <input class="input-hd" type="password" id="db_password" name="db_password" autocomplete="new-password">
                    <div class="hint">XAMPP-এর ডিফল্ট root ব্যবহারকারীর পাসওয়ার্ড সাধারণত ফাঁকা</div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-hd btn-primary-hd" style="margin-top:24px">
            সংযোগ করে টেবিল তৈরি করুন
            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </button>
    </form>
</div>
