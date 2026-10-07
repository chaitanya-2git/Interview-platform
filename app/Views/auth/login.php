<?php use App\Support\Ui; ?>
<section class="auth-form-wrap">
    <span class="eyebrow">YOUR BLUJAY WORKSPACE</span>
    <h2>Welcome back.</h2><p class="intro">Sign in to your interview workspace.</p>
    <?php if (isset($errors['form'])): ?><div class="alert danger" role="alert"><?= $e($errors['form']) ?></div><?php endif; ?>
    <form method="post" action="<?= $e($url('login')) ?>" class="form-stack" data-submit>
        <?= Ui::csrf() ?>
        <div class="field"><label for="identity">Phone number <span class="label-note">or admin email / username</span></label><input id="identity" name="identity" autocomplete="username" maxlength="190" required value="<?= $e($old['identity'] ?? '') ?>" placeholder="Enter your sign-in ID"></div>
        <div class="field"><label for="password">Password</label><div class="password-wrap"><input id="password" type="password" name="password" autocomplete="current-password" required maxlength="72" placeholder="Enter your password"><button type="button" class="password-toggle" data-password="password" aria-controls="password" aria-pressed="false">Show</button></div></div>
        <button class="button primary full" type="submit"><span>Sign in</span><?= Ui::icon('arrow') ?></button>
    </form>
    <p class="auth-switch">New to BluJay? <a href="<?= $e($url('register')) ?>">Create an account <?= Ui::icon('arrow') ?></a></p>
    <div class="help-note"><strong>Need help signing in?</strong><p>Contact your BluJay administrator for account assistance.</p></div>
</section>
