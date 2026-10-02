<?php

declare(strict_types=1);

namespace App;

final class View
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Convert a stored UTC timestamp to the app timezone for display. */
    public static function localTime(?string $utc): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }
        $dt = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
        return $dt->setTimezone(new \DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Dhaka')))->format('d M Y, h:i:s A');
    }

    public static function icon(string $name): string
    {
        $paths = [
            'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'chat' => '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>',
            'check' => '<polyline points="20 6 9 17 4 12"/>',
            'user' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'alert' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'logout' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
            'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>',
            'moon' => '<path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>',
            'book' => '<path d="M4 19.5A2.5 2.5 0 016.5 17H20V2H6.5A2.5 2.5 0 004 4.5v15z"/><path d="M20 17v5H6.5A2.5 2.5 0 014 19.5"/>',
            'list' => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/>',
        ];
        return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
    }

    /** Previous / next links that keep the current query filters. */
    public static function pager(int $page, bool $hasNext): string
    {
        $link = static function (int $to): string {
            return '?' . http_build_query(array_merge($_GET, ['page' => $to]));
        };
        $html = '<div class="pager">';
        $html .= $page > 1 ? '<a class="btn-ghost" href="' . self::e($link($page - 1)) . '">← আগের</a>' : '<span></span>';
        $html .= '<span class="muted">পৃষ্ঠা ' . $page . '</span>';
        $html .= $hasNext ? '<a class="btn-ghost" href="' . self::e($link($page + 1)) . '">পরের →</a>' : '<span></span>';
        return $html . '</div>';
    }

    /** Opens the admin page shell (sidebar + main). */
    public static function header(string $title, string $active): void
    {
        $nav = [
            ['index.php', 'home', 'ড্যাশবোর্ড', true],
            ['comments.php', 'chat', 'কমেন্ট', true],
            ['#', 'user', 'মানুষ লাগবে', false],
            ['#', 'book', 'Knowledge Base', false],
            ['logs.php', 'list', 'লগ', true],
            ['#', 'settings', 'সেটিংস', false],
        ];
        $app = self::e(Env::get('APP_NAME', 'FB Page Bot'));
        ?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= self::e($title) ?> · <?= $app ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/admin.css">
<script src="../assets/theme.js"></script>
</head>
<body>
<div class="app-layout">
  <aside class="sidebar" id="sidebar">
    <div class="brand"><span class="brand-dot"></span><?= $app ?></div>
    <nav>
      <?php foreach ($nav as [$href, $icon, $label, $ready]): ?>
        <?php if ($ready): ?>
          <a class="nav-item<?= $active === $href ? ' active' : '' ?>" href="<?= $href ?>"><?= self::icon($icon) ?><?= $label ?></a>
        <?php else: ?>
          <span class="nav-item disabled" title="পরের ফেজে আসছে"><?= self::icon($icon) ?><?= $label ?><span class="soon">শীঘ্রই</span></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <button type="button" class="nav-item as-button" id="theme-toggle"><?= self::icon('moon') ?>ডার্ক / লাইট</button>
      <a class="nav-item" href="logout.php"><?= self::icon('logout') ?>লগআউট</a>
    </div>
  </aside>
  <main class="main-content">
    <div class="topbar">
      <button type="button" class="menu-btn" id="menu-btn" aria-label="মেনু">☰</button>
      <h1><?= self::e($title) ?></h1>
    </div>
        <?php
    }

    public static function footer(): void
    {
        ?>
  </main>
</div>
</body>
</html>
        <?php
    }
}
