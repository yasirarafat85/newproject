<?php
/** ফ্ল্যাশ বার্তা — সফল/ভুল/সতর্কতা। */
$notice = $notice ?? null;
$fieldErrors = errors();
?>
<?php if ($notice !== null): ?>
    <div class="alert-hd alert-<?= e($notice['type']) ?>" data-autohide role="status">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <?php if ($notice['type'] === 'success'): ?>
                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
            <?php elseif ($notice['type'] === 'danger'): ?>
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            <?php else: ?>
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
            <?php endif; ?>
        </svg>
        <span><?= e($notice['message']) ?></span>
    </div>
<?php endif; ?>

<?php if ($fieldErrors !== []): ?>
    <div class="alert-hd alert-danger" role="alert">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <div>
            <strong>নিচের তথ্যগুলো ঠিক করুন:</strong>
            <ul style="margin:6px 0 0;padding-left:18px">
                <?php foreach ($fieldErrors as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
