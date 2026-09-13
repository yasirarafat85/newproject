<?php
/** লগইন / রেজিস্টার — কেন্দ্রীভূত কার্ড। */
use App\Core\View;
?>
<!doctype html>
<html lang="bn">
<head><?= View::partial('partials/head', ['title' => $title ?? '', 'appName' => $appName ?? 'HelpDesk']) ?></head>
<body>
    <div class="auth-wrap">
        <div style="width:100%;max-width:440px">
            <?= View::partial('partials/notice', ['notice' => $notice ?? null]) ?>
            <div class="auth-card"><?= $content ?></div>
            <p style="text-align:center;margin-top:24px;font-size:13px;color:var(--text-faint)">
                <button type="button" id="theme-toggle" class="btn-hd btn-ghost-hd btn-sm-hd">থিম বদলান</button>
            </p>
        </div>
    </div>
    <script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
