<?php
/** অ্যাডমিন পাতার শিরোনাম + প্রধান অ্যাকশন বোতাম। */
$heading   = $heading ?? '';
$subtitle  = $subtitle ?? '';
$actionUrl = $actionUrl ?? null;
$actionText = $actionText ?? '';
?>
<div class="admin-head">
    <div>
        <h2><?= e($heading) ?></h2>
        <?php if ($subtitle !== ''): ?><p class="muted"><?= e($subtitle) ?></p><?php endif; ?>
    </div>
    <?php if ($actionUrl !== null): ?>
        <a href="<?= e(url($actionUrl)) ?>" class="btn-hd btn-primary-hd btn-sm-hd" style="width:auto">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?= e($actionText) ?>
        </a>
    <?php endif; ?>
</div>
