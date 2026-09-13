<?php
/** একটি থ্রেডের সংযুক্ত ফাইলের তালিকা। */
use App\Core\Str;

$files = $files ?? [];
if ($files === []) {
    return;
}
?>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">
    <?php foreach ($files as $file): ?>
        <a href="<?= e(url('/attachments/' . $file['uuid'])) ?>"
           style="display:inline-flex;align-items:center;gap:8px;padding:7px 12px;
                  border:1px solid var(--border);border-radius:var(--radius);
                  background:var(--bg-secondary);font-size:13px;color:var(--text)">
            <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0">
                <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>
            </svg>
            <span><?= e($file['original_name']) ?></span>
            <span class="faint mono" style="font-size:11px"><?= e(Str::humanBytes((int) $file['size_bytes'])) ?></span>
        </a>
    <?php endforeach; ?>
</div>
