<?php use App\Support\Ui; ?>
<section class="auth-form-wrap reset-flow">
    <a class="back-link" href="<?= $e($url('login')) ?>"><?= Ui::icon('arrow') ?>Back to sign in</a>
    <span class="eyebrow">ACCOUNT RECOVERY</span>
    <h2>Forgot your password?</h2>
    <p class="intro">We'll send a 6-digit code to your registered mobile number. Verify it to set a new password.</p>
    <?php if (isset($errors['form'])): ?><div class="alert danger" role="alert"><?= $e($errors['form']) ?></div><?php endif; ?>

    <?php if (!$configured): ?>
        <div class="alert" role="status">Self-service password reset is not available yet. Please contact your BluJay administrator to reset your password.</div>
    <?php else: ?>
        <noscript><div class="alert" role="status">Password reset needs JavaScript to send the verification code. Please enable JavaScript or contact your BluJay administrator.</div></noscript>
        <div class="alert danger" role="alert" data-reset-error hidden></div>

        <form class="form-stack" data-reset-phone novalidate
              data-api-key="<?= $e($firebase['apiKey']) ?>" data-auth-domain="<?= $e($firebase['authDomain']) ?>" data-project-id="<?= $e($firebase['projectId']) ?>"
              data-lookup-url="<?= $e($url('forgot-password/lookup')) ?>">
            <?= Ui::csrf() ?>
            <div class="field"><label for="reset-phone">Registered phone number</label><input id="reset-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" required maxlength="24" placeholder="+91 98765 43210"><p class="field-hint">The number you use to sign in. Include your country code outside India.</p></div>
            <button class="button primary full" type="submit" data-reset-send><span>Send verification code</span><?= Ui::icon('arrow') ?></button>
        </form>

        <form class="form-stack" method="post" action="<?= $e($url('forgot-password/verify')) ?>" data-reset-code hidden novalidate>
            <?= Ui::csrf() ?>
            <input type="hidden" name="id_token" value="">
            <p class="reset-sent">Code sent to <strong data-reset-phone-label></strong>. <button type="button" class="link-button" data-reset-change>Change number</button></p>
            <div class="field"><label for="reset-otp">Verification code</label><input id="reset-otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="6-digit code"></div>
            <button class="button primary full" type="submit" data-reset-verify><span>Verify code</span><?= Ui::icon('arrow') ?></button>
            <p class="reset-resend"><button type="button" class="link-button" data-reset-resend disabled>Resend code</button> <span data-reset-timer></span></p>
        </form>
        <div id="reset-recaptcha"></div>
        <script type="module" src="<?= $e($url('assets/forgot-password.js')) ?>?v=<?= (int) (@filemtime(ROOT . '/public/assets/forgot-password.js') ?: 0) ?>"></script>
    <?php endif; ?>

    <div class="help-note"><strong>Admin account?</strong><p>Administrator passwords are reset by your BluJay operations team.</p></div>
</section>
