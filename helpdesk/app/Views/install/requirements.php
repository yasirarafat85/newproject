<?php title('ইনস্টলেশন'); ?>
<div class="install-wrap">
    <h1 style="font-size:26px;margin-bottom:6px">HelpDesk ইনস্টলেশন</h1>
    <p class="muted" style="margin-bottom:32px">শুরু করার আগে সার্ভারের প্রস্তুতি দেখে নিচ্ছি।</p>

    <div class="install-steps">
        <div class="install-step active"><span class="num">১</span> প্রস্তুতি</div>
        <div class="install-step"><span class="num">২</span> ডেটাবেস</div>
        <div class="install-step"><span class="num">৩</span> অ্যাডমিন</div>
    </div>

    <div class="card-hd">
        <div class="card-hd-head"><h2>সার্ভার পরীক্ষা</h2></div>
        <div class="card-hd-body">
            <?php foreach ($checks as $check): ?>
                <div class="req-row">
                    <span class="req-mark <?= $check['ok'] ? 'ok' : 'no' ?>"><?= $check['ok'] ? '✓' : '✕' ?></span>
                    <span><?= e($check['label']) ?></span>
                    <span class="val"><?= e($check['value']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($allOk): ?>
        <a href="<?= e(url('/install/database')) ?>" class="btn-hd btn-primary-hd" style="margin-top:24px">
            পরবর্তী ধাপ — ডেটাবেস
            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
    <?php else: ?>
        <div class="alert-hd alert-danger" style="margin-top:24px">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>উপরের লাল চিহ্নিত বিষয়গুলো ঠিক করে পাতাটি রিফ্রেশ করুন। php.ini-তে এক্সটেনশন চালু করার পর Apache রিস্টার্ট দিতে ভুলবেন না।</span>
        </div>
    <?php endif; ?>
</div>
