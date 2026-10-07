<?php use App\Support\{Ui, Session}; $admin = $user['role'] === 'ADMIN'; $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); ?>
<!doctype html>
<html lang="en" data-base-path="<?= $e(\App\Support\Config::basePath()) ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153e39">
    <title><?= $e($title) ?> · BluJay Technologies</title>
    <link rel="stylesheet" href="<?= $e($url('assets/app.css')) ?>?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/assets/app.css') ?: 0) ?>">
    <script src="<?= $e($url('assets/app.js')) ?>?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/assets/app.js') ?: 0) ?>" defer></script>
</head>
<body class="<?= $admin ? 'admin-body' : 'candidate-body' ?>">
<a class="skip-link" href="#main">Skip to content</a>
<div class="workspace <?= $admin ? '' : 'candidate-workspace' ?>">
<aside class="sidebar" id="navigation">
    <div class="mobile-drawer-header">
        <div><strong>BluJay Technologies</strong><span><?= $admin ? 'Interview Operations' : 'Candidate workspace' ?></span></div>
        <button class="icon-button mobile-drawer-close" type="button" data-menu aria-controls="navigation" aria-expanded="false" aria-label="Close navigation"><?= Ui::icon('close') ?></button>
    </div>
    <a class="brand" href="<?= $e($url()) ?>">BluJay<span>TECHNOLOGIES</span></a>
    <div class="workspace-label"><span class="status-dot"></span><?= $admin ? 'Operations workspace' : 'Candidate workspace' ?></div>
    <p class="nav-caption"><?= $admin ? 'WORKSPACE' : 'YOUR SPACE' ?></p>
    <nav aria-label="Main navigation">
    <?php $items = $admin ? [['admin', 'Dashboard', 'grid'], ['admin/calendar', 'Calendar', 'calendar'], ['admin/interviews', 'Interviews', 'list'], ['admin/candidates', 'Candidates', 'user'], ['admin/resources', 'Resources', 'monitor'], ['admin/support', 'Support team', 'shield'], ['admin/technologies', 'Technologies', 'layers'], ['admin/reports', 'Reports', 'chart'], ['admin/audit', 'Audit log', 'activity'], ['admin/settings', 'Settings', 'clock']] : [['candidate', 'Home', 'grid'], ['candidate/book', 'Book interview', 'plus'], ['candidate/interviews', 'My interviews', 'calendar'], ['profile', 'My profile', 'user']]; ?>
    <?php foreach ($items as [$path, $label, $icon]): $routePath = rtrim($currentPath, '/'); $selected = $routePath === $url($path) || ($path === 'candidate/interviews' && str_starts_with($routePath, $url('candidate/interview'))) || ($path === 'admin/interviews' && str_starts_with($routePath, $url('admin/interview')) && !str_contains($routePath, '/new')) || ($path === 'admin/candidates' && str_starts_with($routePath, $url('admin/candidate'))); $secondary = $admin && in_array($path, ['admin/audit', 'admin/settings'], true); ?>
        <a class="nav-item <?= $selected ? 'selected' : '' ?><?= $secondary ? ' secondary-nav-item' : '' ?>" href="<?= $e($url($path)) ?>" <?= $selected ? 'aria-current="page"' : '' ?>><?= Ui::icon($icon) ?><?= $e($label) ?><?= $selected ? '<span class="nav-dot"></span>' : '' ?></a>
    <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <div class="timezone"><?= Ui::icon('clock') ?><span>Business time<strong>Asia/Kolkata · IST</strong></span></div>
        <div class="account-summary"><?= Ui::avatar($user) ?><div><strong><?= $e($user['full_name']) ?></strong><span><?= $admin ? 'Administrator' : 'Candidate' ?></span></div></div>
        <form method="post" action="<?= $e($url('logout')) ?>"><?= Ui::csrf() ?><button class="signout" type="submit"><?= Ui::icon('logout') ?>Sign out</button></form>
    </div>
</aside>
<button class="navigation-scrim" type="button" data-menu aria-label="Close navigation"></button>
<div class="workspace-content">
    <header class="topbar">
        <button class="icon-button mobile-menu" type="button" data-menu aria-controls="navigation" aria-expanded="false" aria-label="Toggle navigation"><?= Ui::icon('menu') ?></button>
        <span class="breadcrumb">Interview Operations <span>/</span> <strong><?= $admin ? 'Admin' : 'My workspace' ?></strong></span>
        <?php if($admin):?><div class="global-search" data-global-search><label class="visually-hidden" for="global-search">Search candidates and interviews</label><input id="global-search" type="search" autocomplete="off" placeholder="Search candidates, phone, company…" maxlength="80" aria-expanded="false" aria-controls="global-search-results"><span class="search-spinner" aria-hidden="true"></span><div class="search-results" id="global-search-results" role="region" aria-live="polite" hidden></div></div><?php endif;?>
        <span class="topbar-date"><?= $e((new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('D, d M Y')) ?></span>
    </header>
    <main id="main" class="main-content">
    <?php if ($flash = Session::takeFlash()): ?><div class="toast" role="status"><?= Ui::icon('check') ?><span><?= $e($flash) ?></span><button class="icon-button" data-dismiss type="button" aria-label="Dismiss notification"><?= Ui::icon('close') ?></button></div><?php endif; ?>
    <?= $content ?>
    <footer class="page-footer">BluJay Technologies <span>Interview Operations · All times in IST</span></footer>
    </main>
</div>
</div>
<?php if (!$admin): ?><nav class="mobile-bottom-nav" aria-label="Mobile navigation"><a href="<?= $e($url('candidate')) ?>" <?= $currentPath === $url('candidate') ? 'aria-current="page"' : '' ?>><?= Ui::icon('grid') ?>Home</a><a href="<?= $e($url('candidate/book')) ?>" <?= $currentPath === $url('candidate/book') ? 'aria-current="page"' : '' ?>><?= Ui::icon('plus') ?>Book</a><a href="<?= $e($url('candidate/interviews')) ?>" <?= str_starts_with($currentPath, $url('candidate/interview')) ? 'aria-current="page"' : '' ?>><?= Ui::icon('calendar') ?>Interviews</a><a href="<?= $e($url('profile')) ?>" <?= $currentPath === $url('profile') ? 'aria-current="page"' : '' ?>><?= Ui::icon('user') ?>Profile</a></nav><?php else: ?><?php $adminMore=!in_array($currentPath,[$url('admin'),$url('admin/calendar'),$url('admin/interviews')],true);?><nav class="mobile-bottom-nav admin-mobile-nav" aria-label="Admin mobile navigation"><a href="<?= $e($url('admin')) ?>" <?= $currentPath===$url('admin')?'aria-current="page"':'' ?>><?= Ui::icon('grid') ?>Dashboard</a><a href="<?= $e($url('admin/calendar')) ?>" <?= str_starts_with($currentPath,$url('admin/calendar'))?'aria-current="page"':'' ?>><?= Ui::icon('calendar') ?>Calendar</a><a href="<?= $e($url('admin/interviews')) ?>" <?= str_starts_with($currentPath,$url('admin/interview'))?'aria-current="page"':'' ?>><?= Ui::icon('list') ?>Interviews</a><button type="button" data-menu aria-controls="navigation" aria-label="Open more navigation" <?= $adminMore?'aria-current="page"':'' ?>><?= Ui::icon('menu') ?>More</button></nav><?php endif; ?>
<dialog id="confirmation-dialog" class="modal" aria-labelledby="confirmation-title"><form method="dialog"><div class="modal-icon"><?= Ui::icon('shield') ?></div><h2 id="confirmation-title">Confirm this change</h2><p id="confirmation-message"></p><div class="modal-actions"><button class="button secondary" value="cancel" id="confirmation-cancel">Go back</button><button class="button primary" value="confirm" id="confirmation-confirm">Confirm</button></div></form></dialog>
</body>
</html>
