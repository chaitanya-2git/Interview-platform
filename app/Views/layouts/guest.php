<?php use App\Support\{Ui, Session}; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153e39">
    <title><?= $e($title) ?> · BluJay Technologies</title>
    <link rel="stylesheet" href="<?= $e($url('assets/app.css')) ?>">
    <script src="<?= $e($url('assets/app.js')) ?>" defer></script>
</head>
<body class="guest-body">
<a class="skip-link" href="#main">Skip to content</a>
<div class="auth-layout">
    <aside class="auth-story">
        <a class="brand brand-light" href="<?= $e($url()) ?>">BluJay<span>TECHNOLOGIES</span></a>
        <div class="story-copy">
            <span class="eyebrow light">INTERVIEW OPERATIONS</span>
            <h1>A little more clarity.<br>A lot more confidence.</h1>
            <p>Your profile, your team, and the support behind your next opportunity. Together in one place.</p>
            <div class="story-line"></div>
            <div class="story-feature"><?= Ui::icon('user') ?><div><strong>Built around you</strong><span>One profile for your interview journey.</span></div></div>
            <div class="story-feature"><?= Ui::icon('shield') ?><div><strong>A private workspace</strong><span>Secure access for candidates and your operations team.</span></div></div>
        </div>
        <div class="story-footer"><span>BluJay Interview Operations</span><span>Asia / Kolkata</span></div>
    </aside>
    <main id="main" class="auth-main">
        <a class="brand mobile-brand" href="<?= $e($url()) ?>">BluJay<span>TECHNOLOGIES</span></a>
        <?php if ($flash = Session::takeFlash()): ?><div class="alert success" role="status"><?= $e($flash) ?></div><?php endif; ?>
        <?= $content ?>
        <footer class="auth-footer"><?= Ui::icon('shield') ?> A private BluJay Technologies workspace</footer>
    </main>
</div>
</body>
</html>
