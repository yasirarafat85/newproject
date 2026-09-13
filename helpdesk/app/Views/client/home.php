<?php
/** গ্রাহক পোর্টালের হোম — টিকেট খোলা, KB সার্চ, টপিক। */
title('সহায়তা কেন্দ্র');
?>

<section class="portal-hero">
    <h1>কীভাবে সাহায্য করতে পারি?</h1>
    <p><?= e(setting('portal_tagline', 'আমরা সাহায্য করতে প্রস্তুত')) ?></p>

    <form method="get" action="<?= e(url('/kb/search')) ?>" style="max-width:520px;margin:28px auto 0;display:flex;gap:10px">
        <input class="input-hd" type="search" name="q" placeholder="আপনার সমস্যাটি লিখুন…" aria-label="নলেজ বেসে খুঁজুন">
        <button type="submit" class="btn-hd btn-primary-hd" style="width:auto;flex-shrink:0">খুঁজুন</button>
    </form>

    <div style="display:flex;gap:12px;justify-content:center;margin-top:20px;flex-wrap:wrap">
        <a href="<?= e(url('/tickets/new')) ?>" class="btn-hd btn-success-hd" style="width:auto">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            নতুন টিকেট খুলুন
        </a>
        <?php if (($openCount ?? 0) > 0): ?>
            <a href="<?= e(url('/tickets')) ?>" class="btn-hd btn-ghost-hd" style="width:auto">
                আমার <?= (int) $openCount ?>টি চলমান টিকেট
            </a>
        <?php else: ?>
            <a href="<?= e(url('/tickets/check')) ?>" class="btn-hd btn-ghost-hd" style="width:auto">টিকেটের অবস্থা দেখুন</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($topics !== []): ?>
<section style="margin-top:48px">
    <h2 style="font-size:19px;margin-bottom:16px">কোন বিষয়ে সাহায্য দরকার?</h2>
    <div class="topic-grid">
        <?php foreach ($topics as $topic): ?>
            <a class="topic-card" href="<?= e(url('/tickets/new?topic=' . $topic['id'])) ?>">
                <span class="stat-icon brand" style="width:38px;height:38px">
                    <svg viewBox="0 0 24 24" style="width:19px;height:19px"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </span>
                <span style="font-weight:600;font-size:14.5px"><?= e($topic['name']) ?></span>
                <span class="arrow">→</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($articles !== []): ?>
<section style="margin-top:48px">
    <h2 style="font-size:19px;margin-bottom:16px">জনপ্রিয় সহায়িকা</h2>
    <div class="card-hd">
        <div class="card-hd-body" style="padding:0">
            <?php foreach ($articles as $article): ?>
                <a href="<?= e(url('/kb/' . $article['slug'])) ?>"
                   style="display:block;padding:16px 20px;border-bottom:1px solid var(--border);color:var(--text)">
                    <div style="font-weight:600;margin-bottom:3px"><?= e($article['title']) ?></div>
                    <?php if (($article['excerpt'] ?? '') !== ''): ?>
                        <div class="muted" style="font-size:13.5px"><?= e($article['excerpt']) ?></div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
