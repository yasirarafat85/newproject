<?php title('টিকেট খুঁজুন'); ?>

<div style="max-width:480px;margin:0 auto">
    <h1 style="font-size:24px;margin-bottom:6px">টিকেটের অবস্থা দেখুন</h1>
    <p class="muted" style="margin-bottom:28px">অ্যাকাউন্ট না থাকলেও টিকেট নম্বর ও ইমেইল দিয়ে দেখতে পারবেন।</p>

    <form method="post" action="<?= e(url('/tickets/check')) ?>" data-once>
        <?= csrf_field() ?>
        <div class="card-hd"><div class="card-hd-body">
            <div class="field">
                <label for="number">টিকেট নম্বর <span class="required">*</span></label>
                <input class="input-hd mono" type="text" id="number" name="number" value="<?= e(old('number')) ?>"
                       required placeholder="250913-0042" autofocus>
                <div class="hint">টিকেট খোলার সময় দেওয়া নম্বরটি</div>
            </div>
            <div class="field" style="margin-bottom:0">
                <label for="email">ইমেইল <span class="required">*</span></label>
                <input class="input-hd" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required>
            </div>
        </div></div>
        <button type="submit" class="btn-hd btn-primary-hd btn-block" style="margin-top:20px">টিকেট দেখুন</button>
    </form>
</div>
