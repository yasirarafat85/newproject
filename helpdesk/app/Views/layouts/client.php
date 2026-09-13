<?php
/** গ্রাহক পোর্টালের লেআউট। */
use App\Core\View;

$client = $authClient ?? null;
$path = $currentPath ?? '/';
?>
<!doctype html>
<html lang="bn">
<head><?= View::partial('partials/head', ['title' => $title ?? '', 'appName' => $appName ?? 'HelpDesk']) ?></head>
<body>
<header class="portal-header">
    <div class="portal-header-in">
        <a href="<?= e(url('/')) ?>" class="sidebar-brand" style="border:none;padding:0">
            <span class="brand-mark">HD</span>
            <span><?= e($companyName ?? 'HelpDesk') ?></span>
        </a>
        <nav class="portal-nav">
            <a href="<?= e(url('/')) ?>" class="<?= $path === '/' ? 'active' : '' ?>">হোম</a>
            <a href="<?= e(url('/tickets/new')) ?>" class="<?= str_starts_with($path, '/tickets/new') ? 'active' : '' ?>">নতুন টিকেট</a>
            <?php if ($client !== null): ?>
                <a href="<?= e(url('/tickets')) ?>" class="<?= $path === '/tickets' ? 'active' : '' ?>">আমার টিকেট</a>
                <form method="post" action="<?= e(url('/logout')) ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-hd btn-ghost-hd btn-sm-hd">লগআউট</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('/tickets/check')) ?>">টিকেট খুঁজুন</a>
                <a href="<?= e(url('/login')) ?>" class="btn-hd btn-primary-hd btn-sm-hd" style="color:#fff">লগইন</a>
            <?php endif; ?>
            <button type="button" id="theme-toggle" class="icon-btn" aria-label="থিম বদলান">
                <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
            </button>
        </nav>
    </div>
</header>

<main class="portal-main">
    <?= View::partial('partials/notice', ['notice' => $notice ?? null]) ?>
    <?= $content ?>
</main>

<footer class="portal-footer">
    © <?= date('Y') ?> <?= e($companyName ?? 'HelpDesk') ?> · সহায়তা কেন্দ্র
</footer>

<script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
