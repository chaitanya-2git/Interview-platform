<?php
use App\Support\{Ui, Session};
$admin = $user['role'] === 'ADMIN';
$partner = $user['role'] === 'CONSULTANCY';
$currentPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$area = $admin ? 'admin' : ($partner ? 'consultancy' : 'candidate');
$roleLabel = $admin ? 'Administrator' : ($partner ? 'Consultancy' : 'Candidate');
$asset = fn (string $file) => $url('assets/' . $file) . '?v=' . (int) (@filemtime(dirname(__DIR__, 3) . '/public/assets/' . $file) ?: 0);
/** A nav entry is active on its own page and on the detail pages that belong to it. */
$isActive = function (string $path) use ($url, $currentPath): bool {
    $target = rtrim($url($path), '/');
    if ($currentPath === $target) return true;
    $families = [
        'candidate/interviews' => 'candidate/interview', 'consultancy/interviews' => 'consultancy/interview',
        'consultancy/students' => 'consultancy/student', 'admin/candidates' => 'admin/candidate',
        'admin/consultancies' => 'admin/consultanc', 'admin/audit' => 'admin/audit', 'admin/calendar' => 'admin/calendar',
    ];
    if ($path === 'admin/interviews') return str_starts_with($currentPath, $url('admin/interview')) && !str_contains($currentPath, '/new');
    return isset($families[$path]) && str_starts_with($currentPath, $url($families[$path]));
};
$groups = $admin ? [
    'Daily work' => [['admin', 'Dashboard', 'grid'], ['admin/calendar', 'Calendar', 'calendar'], ['admin/interviews', 'Interviews', 'list'], ['admin/locations', 'Assign locations', 'pin']],
    'People' => [['admin/candidates', 'Candidates', 'user'], ['admin/consultancies', 'Consultancies', 'briefcase']],
    'Insights' => [['admin/reports', 'Reports', 'chart'], ['admin/audit', 'Audit log', 'activity']],
    'Setup' => [['admin/resources', 'Resources', 'monitor'], ['admin/support', 'Support team', 'shield'], ['admin/technologies', 'Technologies', 'layers'], ['admin/settings', 'Settings', 'clock']],
] : ['' => $partner
    ? [['consultancy', 'Home', 'grid'], ['consultancy/book', 'Book interview', 'plus'], ['consultancy/interviews', 'Interviews', 'calendar'], ['consultancy/students', 'Students', 'user'], ['consultancy/reports', 'Reports', 'chart']]
    : [['candidate', 'Home', 'grid'], ['candidate/book', 'Book interview', 'plus'], ['candidate/interviews', 'My interviews', 'calendar'], ['profile', 'My profile', 'user']]];
$mobileTabs = $admin
    ? [['admin', 'Home', 'grid'], ['admin/calendar', 'Calendar', 'calendar'], ['admin/interviews', 'Interviews', 'list'], ['admin/locations', 'Locations', 'pin']]
    : ($partner
        ? [['consultancy', 'Home', 'grid'], ['consultancy/interviews', 'Interviews', 'calendar'], ['consultancy/book', 'Book', 'plus'], ['consultancy/students', 'Students', 'user'], ['consultancy/reports', 'Reports', 'chart']]
        : [['candidate', 'Home', 'grid'], ['candidate/interviews', 'Interviews', 'calendar'], ['candidate/book', 'Book', 'plus'], ['profile', 'Profile', 'user']]);
$onBookPage = $isActive($area . '/book');
?>
<!doctype html>
<html lang="en" data-base-path="<?= $e(\App\Support\Config::basePath()) ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b4a7e">
    <title><?= $e($title) ?> · BluJay Technologies</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $e($url('assets/brand/blujay-mark-32.png')) ?>">
    <link rel="apple-touch-icon" href="<?= $e($url('assets/brand/blujay-mark-192.png')) ?>">
    <link rel="stylesheet" href="<?= $e($asset('app.css')) ?>">
    <script src="<?= $e($asset('app.js')) ?>" defer></script>
</head>
<body class="<?= $admin ? 'admin-body' : 'candidate-body' ?> shell-v2 shell-<?= $e($area) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<?php ob_start(); ?>
<details class="account-menu" data-account-menu>
    <summary aria-label="Account menu"><?= Ui::avatar($user, null, 'account-avatar') ?><span class="account-menu-name"><strong><?= $e($user['full_name']) ?></strong><small><?= $e($roleLabel) ?></small></span><?= Ui::icon('down', 'account-caret') ?></summary>
    <div class="account-menu-panel">
        <div class="account-menu-head"><?= Ui::avatar($user, null, 'account-avatar') ?><span><strong><?= $e($user['full_name']) ?></strong><small><?= $e($roleLabel) ?> · All times in IST</small></span></div>
        <?php if (!$admin && !$partner): ?><a href="<?= $e($url('profile')) ?>"><?= Ui::icon('user') ?>My profile</a><a href="<?= $e($url('candidate/interviews')) ?>"><?= Ui::icon('calendar') ?>My interviews</a><?php endif; ?>
        <?php if ($partner): ?><a href="<?= $e($url('consultancy/students')) ?>"><?= Ui::icon('user') ?>Students</a><a href="<?= $e($url('consultancy/reports')) ?>"><?= Ui::icon('chart') ?>Reports</a><?php endif; ?>
        <?php if ($admin): ?><a href="<?= $e($url('admin/settings')) ?>"><?= Ui::icon('clock') ?>Settings</a><a href="<?= $e($url('admin/audit')) ?>"><?= Ui::icon('activity') ?>Audit log</a><?php endif; ?>
        <form method="post" action="<?= $e($url('logout')) ?>"><?= Ui::csrf() ?><button type="submit"><?= Ui::icon('logout') ?>Sign out</button></form>
    </div>
</details>
<?php $accountMenu = ob_get_clean(); ?>
<?php if ($admin): ?>
<div class="workspace">
<aside class="sidebar" id="navigation">
    <div class="mobile-drawer-header">
        <div><img class="drawer-logo" src="<?= $e($url('assets/brand/blujay-logo.png')) ?>" alt="BluJay Technologies" width="640" height="149"><span>Interview operations</span></div>
        <button class="icon-button mobile-drawer-close" type="button" data-menu aria-controls="navigation" aria-expanded="false" aria-label="Close navigation"><?= Ui::icon('close') ?></button>
    </div>
    <a class="brand brand-logo" href="<?= $e($url()) ?>"><img src="<?= $e($url('assets/brand/blujay-logo.png')) ?>" alt="BluJay Technologies" width="640" height="149"></a>
    <a class="button primary sidebar-cta" href="<?= $e($url('admin/interview/new')) ?>"><?= Ui::icon('plus') ?>New interview</a>
    <nav aria-label="Main navigation" class="grouped-nav">
    <?php foreach ($groups as $caption => $items): ?>
        <p class="nav-caption"><?= $e(strtoupper($caption)) ?></p>
        <?php foreach ($items as [$path, $label, $icon]): $selected = $isActive($path); ?>
        <a class="nav-item<?= $selected ? ' selected' : '' ?>" href="<?= $e($url($path)) ?>" <?= $selected ? 'aria-current="page"' : '' ?>><?= Ui::icon($icon) ?><?= $e($label) ?></a>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <form method="post" action="<?= $e($url('logout')) ?>"><?= Ui::csrf() ?><button class="signout" type="submit"><?= Ui::icon('logout') ?>Sign out</button></form>
    </div>
</aside>
<button class="navigation-scrim" type="button" data-menu aria-label="Close navigation"></button>
<div class="workspace-content">
    <header class="topbar">
        <button class="icon-button mobile-menu" type="button" data-menu aria-controls="navigation" aria-expanded="false" aria-label="Open navigation"><?= Ui::icon('menu') ?></button>
        <a class="topbar-logo" href="<?= $e($url()) ?>"><img src="<?= $e($url('assets/brand/blujay-logo.png')) ?>" alt="BluJay Technologies" width="640" height="149"></a>
        <div class="global-search" data-global-search><label class="visually-hidden" for="global-search">Search candidates and interviews</label><input id="global-search" type="search" autocomplete="off" placeholder="Search name, phone, company or “Gen AI 2+”" maxlength="80" aria-expanded="false" aria-controls="global-search-results"><span class="search-spinner" aria-hidden="true"></span><div class="search-results" id="global-search-results" role="region" aria-live="polite" hidden></div></div>
        <span class="topbar-date"><?= Ui::icon('clock') ?><?= $e((new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('D, d M')) ?> · IST</span>
        <?= $accountMenu ?>
    </header>
<?php else: ?>
<div class="app-shell">
    <header class="app-header">
        <div class="app-header-inner">
            <a class="app-logo" href="<?= $e($url()) ?>"><img src="<?= $e($url('assets/brand/blujay-logo.png')) ?>" alt="BluJay Technologies" width="640" height="149"></a>
            <nav class="app-nav" aria-label="Main navigation">
            <?php foreach ($groups[''] as [$path, $label, $icon]): if ($path === $area . '/book') continue; $selected = $isActive($path); ?>
                <a href="<?= $e($url($path)) ?>" <?= $selected ? 'aria-current="page"' : '' ?>><?= Ui::icon($icon) ?><?= $e($label) ?></a>
            <?php endforeach; ?>
            </nav>
            <div class="app-header-actions">
                <?php if (!$onBookPage): ?><a class="button primary header-cta" href="<?= $e($url($area . '/book')) ?>"><?= Ui::icon('plus') ?>Book interview</a><?php endif; ?>
                <?= $accountMenu ?>
            </div>
        </div>
    </header>
<?php endif; ?>
    <main id="main" class="main-content">
    <?php if ($flash = Session::takeFlash()): ?><div class="toast" role="status"><?= Ui::icon('check') ?><span><?= $e($flash) ?></span><button class="icon-button" data-dismiss type="button" aria-label="Dismiss notification"><?= Ui::icon('close') ?></button></div><?php endif; ?>
    <?= $content ?>
    <footer class="page-footer">BluJay Technologies <span>All times in IST (Asia/Kolkata)</span></footer>
    </main>
<?php if ($admin): ?></div></div><?php else: ?></div><?php endif; ?>
<nav class="mobile-bottom-nav<?= $admin ? ' admin-mobile-nav' : '' ?>" aria-label="Mobile navigation">
<?php foreach ($mobileTabs as [$path, $label, $icon]): $selected = $isActive($path); ?>
    <a class="<?= str_ends_with($path, '/book') ? 'tab-book' : '' ?>" href="<?= $e($url($path)) ?>" <?= $selected ? 'aria-current="page"' : '' ?>><span class="tab-icon"><?= Ui::icon($icon) ?></span><?= $e($label) ?></a>
<?php endforeach; ?>
<?php if ($admin): $more = !array_filter($mobileTabs, fn ($tab) => $isActive($tab[0])); ?><button type="button" data-menu aria-controls="navigation" aria-label="Open all navigation" <?= $more ? 'aria-current="page"' : '' ?>><span class="tab-icon"><?= Ui::icon('menu') ?></span>More</button><?php endif; ?>
</nav>
<?php require ROOT . '/app/Views/components/help-bot.php'; ?>
<dialog id="confirmation-dialog" class="modal" aria-labelledby="confirmation-title"><form method="dialog"><div class="modal-icon"><?= Ui::icon('shield') ?></div><h2 id="confirmation-title">Confirm this change</h2><p id="confirmation-message"></p><div class="modal-actions"><button class="button secondary" value="cancel" id="confirmation-cancel">Go back</button><button class="button primary" value="confirm" id="confirmation-confirm">Confirm</button></div></form></dialog>
</body>
</html>
