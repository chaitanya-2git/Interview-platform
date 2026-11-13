<?php use App\Support\Ui; ?>
<section class="auth-form-wrap account-type-wrap">
    <span class="eyebrow">WELCOME TO BLUJAY</span>
    <h2>How will you use BluJay?</h2>
    <p class="intro">Choose the account type for this Google account. You only do this once.</p>
    <?php require ROOT . '/app/Views/components/google-identity.php'; ?>
    <div class="account-type-options">
        <a class="account-type-card" href="<?= $e($url('register/google?as=candidate')) ?>">
            <span class="account-type-icon"><?= Ui::icon('user') ?></span>
            <span><strong>I'm a candidate</strong><small>Book interview support slots for yourself.</small></span>
            <?= Ui::icon('arrow') ?>
        </a>
        <a class="account-type-card" href="<?= $e($url('register/consultancy')) ?>">
            <span class="account-type-icon"><?= Ui::icon('briefcase') ?></span>
            <span><strong>I'm a consultancy</strong><small>Add your students and book slots on their behalf. Needs BluJay approval.</small></span>
            <?= Ui::icon('arrow') ?>
        </a>
    </div>
</section>
