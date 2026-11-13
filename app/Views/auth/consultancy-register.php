<?php use App\Support\Ui; ?>
<section class="auth-form-wrap register-wrap">
    <span class="eyebrow">PARTNER CONSULTANCY</span>
    <h2>Register your consultancy.</h2>
    <p class="intro">Tell us about your consultancy. After BluJay approves it, you can add your students and book interview slots for them.</p>
    <?php require ROOT . '/app/Views/components/google-identity.php'; ?>
    <?php if ($errors): ?><div class="alert danger" role="alert"><?= $e($errors['form'] ?? 'Please review the highlighted fields below.') ?></div><?php endif; ?>
    <form method="post" action="<?= $e($url('register/consultancy')) ?>" class="form-stack registration-form" data-submit>
        <?= Ui::csrf() ?>
        <section class="registration-section">
            <h3>Consultancy details</h3>
            <div class="registration-fields">
                <div class="field"><label for="name">Consultancy name</label><input id="name" name="name" required minlength="2" maxlength="160" autocomplete="organization" value="<?= $e($old['name'] ?? '') ?>" placeholder="e.g. Sri Sai Career Consultancy"<?= Ui::invalid($errors,'name') ?>><?= Ui::error($errors,'name') ?></div>
                <div class="field"><label for="contact_name">Contact person</label><input id="contact_name" name="contact_name" required minlength="2" maxlength="120" autocomplete="name" value="<?= $e($old['contact_name'] ?? '') ?>" placeholder="Your full name"<?= Ui::invalid($errors,'contact_name') ?>><?= Ui::error($errors,'contact_name') ?></div>
                <div class="field"><label for="phone">Contact phone</label><input id="phone" name="phone" type="tel" required maxlength="24" autocomplete="tel" value="<?= $e($old['phone'] ?? '') ?>" placeholder="+91 98765 43210"<?= Ui::invalid($errors,'phone') ?>><?= Ui::error($errors,'phone') ?></div>
                <div class="field"><label for="city">City <span class="label-note">optional</span></label><input id="city" name="city" maxlength="80" autocomplete="address-level2" value="<?= $e($old['city'] ?? '') ?>" placeholder="e.g. Hyderabad"<?= Ui::invalid($errors,'city') ?>><?= Ui::error($errors,'city') ?></div>
                <div class="field"><label for="website">Website <span class="label-note">optional</span></label><input id="website" name="website" maxlength="190" inputmode="url" value="<?= $e($old['website'] ?? '') ?>" placeholder="example.com"<?= Ui::invalid($errors,'website') ?>><?= Ui::error($errors,'website') ?></div>
            </div>
        </section>
        <button class="button primary full" type="submit">Submit for approval <?= Ui::icon('arrow') ?></button>
    </form>
    <p class="auth-switch"><a href="<?= $e($url('register/google')) ?>">← Choose a different account type</a></p>
</section>
