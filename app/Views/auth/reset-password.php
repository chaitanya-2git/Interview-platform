<?php use App\Support\Ui; ?>
<section class="auth-form-wrap reset-flow">
    <span class="eyebrow">ACCOUNT RECOVERY</span>
    <h2>Choose a new password.</h2>
    <p class="intro">Phone number <strong><?= $e($phone) ?></strong> verified. Set a new password for your account.</p>
    <?php if (isset($errors['form'])): ?><div class="alert danger" role="alert"><?= $e($errors['form']) ?></div><?php endif; ?>
    <form method="post" action="<?= $e($url('reset-password')) ?>" class="form-stack" data-submit>
        <?= Ui::csrf() ?>
        <div class="field"><label for="password">New password</label><div class="password-wrap"><input id="password" type="password" name="password" autocomplete="new-password" required minlength="12" maxlength="72" placeholder="At least 12 characters"<?= Ui::invalid($errors,'password') ?>><button type="button" class="password-toggle" data-password="password" aria-controls="password" aria-pressed="false">Show</button></div><?= Ui::error($errors,'password') ?></div>
        <div class="field"><label for="password_confirmation">Confirm new password</label><div class="password-wrap"><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required minlength="12" maxlength="72" placeholder="Type it again"<?= Ui::invalid($errors,'password_confirmation') ?>><button type="button" class="password-toggle" data-password="password_confirmation" aria-controls="password_confirmation" aria-pressed="false">Show</button></div><?= Ui::error($errors,'password_confirmation') ?></div>
        <button class="button primary full" type="submit"><span>Save new password</span><?= Ui::icon('arrow') ?></button>
    </form>
    <p class="auth-switch"><a href="<?= $e($url('login')) ?>">Back to sign in</a></p>
</section>
