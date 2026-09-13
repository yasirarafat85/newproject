<?php title('ইনস্টলেশন সম্পন্ন'); ?>
<div class="install-wrap" style="text-align:center">
    <div class="stat-icon green" style="width:64px;height:64px;margin:0 auto 20px;border-radius:18px">
        <svg viewBox="0 0 24 24" style="width:32px;height:32px"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h1 style="font-size:28px;margin-bottom:8px">ইনস্টলেশন সম্পন্ন!</h1>
    <p class="muted" style="margin-bottom:32px">আপনি ইতিমধ্যে <?= e($email) ?> দিয়ে লগইন করা আছেন।</p>

    <div class="card-hd" style="text-align:left">
        <div class="card-hd-head"><h2>এখন যা করবেন</h2></div>
        <div class="card-hd-body">
            <ol style="margin:0;padding-left:20px;line-height:2">
                <li><strong>ক্রন চালু করুন</strong> — ইমেইল ও SLA কাজের জন্য <code>cron/run.php</code> প্রতি মিনিটে চালাতে হবে</li>
                <li><strong>ডিপার্টমেন্ট ও এজেন্ট</strong> যোগ করুন অ্যাডমিন প্যানেল থেকে</li>
                <li><strong>ইমেইল অ্যাকাউন্ট</strong> যুক্ত করুন, যাতে ইমেইল থেকেই টিকেট তৈরি হয়</li>
                <li>প্রোডাকশনে <code>.env</code>-এ <code>APP_DEBUG=false</code> আছে কি না দেখে নিন</li>
            </ol>
        </div>
    </div>

    <div style="display:flex;gap:12px;justify-content:center;margin-top:28px;flex-wrap:wrap">
        <a href="<?= e(url('/agent')) ?>" class="btn-hd btn-primary-hd" style="width:auto">এজেন্ট প্যানেলে যান</a>
        <a href="<?= e(url('/')) ?>" class="btn-hd btn-ghost-hd" style="width:auto">গ্রাহক পোর্টাল দেখুন</a>
    </div>
</div>
