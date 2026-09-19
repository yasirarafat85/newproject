<?php
/** এজেন্ট ও অ্যাডমিন প্যানেলের শেল — সাইডবার + টপবার। */
use App\Core\Auth;
use App\Core\Str;
use App\Core\View;

$agent = $authAgent ?? null;
$path  = $currentPath ?? '/agent';

/** নেভিগেশন আইটেম: [পথ, লেবেল, SVG path, পারমিশন|null] */
$navMain = [
    ['/agent',          'ড্যাশবোর্ড', '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>', null],
    ['/agent/tickets',  'টিকেট',     '<path d="M4 4h16v5a3 3 0 000 6v5H4v-5a3 3 0 000-6V4z"/>', null],
    ['/agent/users',    'গ্রাহক',     '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>', 'user.view'],
    ['/agent/kb',       'নলেজ বেস',   '<path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>', 'kb.view'],
];

$navAdmin = [
    ['/admin',             'অ্যাডমিন হোম',  '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>', null],
    ['/admin/agents',      'এজেন্ট',        '<path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87"/>', 'admin.agents'],
    ['/admin/departments', 'ডিপার্টমেন্ট',  '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/>', 'admin.departments'],
    ['/admin/teams',       'টিম',           '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>', 'admin.teams'],
    ['/admin/roles',       'রোল ও পারমিশন', '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>', 'admin.roles'],
    ['/admin/topics',      'হেল্প টপিক',    '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>', 'admin.topics'],
    ['/admin/logs',        'অ্যাক্টিভিটি লগ', '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>', 'admin.logs'],
    ['/admin/settings',    'সেটিংস',        '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06A1.65 1.65 0 0015 19.4a1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09A1.65 1.65 0 0015 4.6a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9c.14.35.4.64.73.84.3.19.65.3 1 .31H21a2 2 0 010 4h-.09c-.35 0-.7.11-1 .31z"/>', 'admin.settings'],
];

// ড্যাশবোর্ড লিংকগুলো শুধু হুবহু মিললেই সক্রিয়, নইলে সব সাব-পাতাতেই সক্রিয় দেখাত
$isActive = static function (string $item) use ($path): bool {
    return in_array($item, ['/agent', '/admin'], true)
        ? $path === $item
        : str_starts_with($path, $item);
};
?>
<!doctype html>
<html lang="bn">
<head><?= View::partial('partials/head', ['title' => $title ?? '', 'appName' => $appName ?? 'HelpDesk']) ?></head>
<body>
<div class="app-shell">
    <div class="sidebar-backdrop"></div>

    <aside class="sidebar">
        <a href="<?= e(url('/agent')) ?>" class="sidebar-brand">
            <span class="brand-mark">HD</span>
            <span><?= e($companyName ?? 'HelpDesk') ?></span>
        </a>

        <nav class="sidebar-nav">
            <?php foreach ($navMain as [$href, $label, $icon, $permission]): ?>
                <?php if ($permission !== null && !Auth::hasPermission($permission)) { continue; } ?>
                <a href="<?= e(url($href)) ?>" class="nav-item <?= $isActive($href) ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>

            <?php
            // পারমিশন null মানে "অ্যাডমিন প্যানেলে ঢুকতে পারলেই দেখা যাবে"
            $adminItems = array_filter(
                $navAdmin,
                static fn (array $item): bool => $item[3] === null || Auth::hasPermission($item[3])
            );
            ?>
            <?php if ($adminItems !== []): ?>
                <div class="nav-section small-label">অ্যাডমিন</div>
                <?php foreach ($adminItems as [$href, $label, $icon]): ?>
                    <a href="<?= e(url($href)) ?>" class="nav-item <?= $isActive($href) ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg>
                        <span><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>

        <div class="sidebar-foot">
            <?php if ($agent !== null): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:8px 6px">
                    <span class="avatar"><?= e(Str::initials((string) $agent['name'])) ?></span>
                    <div style="min-width:0;flex:1">
                        <div style="font-size:13.5px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($agent['name']) ?></div>
                        <div style="font-size:11.5px;color:var(--text-faint)"><?= $agent['is_admin'] ? 'অ্যাডমিন' : 'এজেন্ট' ?></div>
                    </div>
                </div>
                <form method="post" action="<?= e(url('/agent/logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-hd btn-ghost-hd btn-sm-hd btn-block">লগআউট</button>
                </form>
            <?php endif; ?>
        </div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <button type="button" class="icon-btn menu-toggle" aria-label="মেনু">
                <svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <h1><?= e($title ?? 'ড্যাশবোর্ড') ?></h1>

            <div class="spacer"></div>

            <a href="<?= e(url('/agent/notifications')) ?>" class="icon-btn" aria-label="নোটিফিকেশন">
                <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                <?php if (($unreadCount ?? 0) > 0): ?>
                    <span class="badge-count"><?= (int) $unreadCount > 99 ? '99+' : (int) $unreadCount ?></span>
                <?php endif; ?>
            </a>

            <button type="button" id="theme-toggle" class="icon-btn" aria-label="থিম বদলান">
                <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
            </button>
        </header>

        <main class="main-content">
            <?= View::partial('partials/notice', ['notice' => $notice ?? null]) ?>
            <?= $content ?>
        </main>
    </div>
</div>

<script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
