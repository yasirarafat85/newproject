<?php
/**
 * স্ট্যাটাস ও প্রায়োরিটি ব্যাজ।
 * রঙের পাশাপাশি লেখাও থাকে, তাই রং চিনতে না পারলেও বোঝা যায়।
 */
$kind  = $kind ?? 'status';
$name  = $name ?? '';
$color = $color ?? '#6B7280';
?>
<span class="badge-hd" style="background:<?= e($color) ?>1f;color:<?= e($color) ?>">
    <?php if ($kind === 'priority'): ?><span class="dot"></span><?php endif; ?>
    <?= e($name) ?>
</span>
