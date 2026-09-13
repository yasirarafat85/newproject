<?php title('ত্রুটি ' . $status); ?>
<div class="error-wrap">
    <div>
        <div class="error-code"><?= (int) $status ?></div>
        <h1 style="font-size:20px;margin:12px 0 8px">
            <?= $status === 404 ? 'পৃষ্ঠাটি পাওয়া যায়নি' : ($status === 403 ? 'প্রবেশাধিকার নেই' : 'কিছু একটা ভুল হয়েছে') ?>
        </h1>
        <p class="muted" style="max-width:46ch;margin:0 auto 24px"><?= e($message) ?></p>
        <a href="<?= e(url('/')) ?>" class="btn-hd btn-ghost-hd" style="width:auto;display:inline-flex">হোমে ফিরুন</a>
    </div>
</div>
