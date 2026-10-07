<?php use App\Support\Ui; ?>
<section class="auth-form-wrap register-wrap">
    <span class="eyebrow">LET’S GET YOU SET UP</span>
    <h2>Your next chapter <br>starts here.</h2><p class="intro">Create your candidate profile. Make it yours.</p>
    <?php if (!$technologies): ?>
        <div class="alert" role="status">Candidate registration will open when your administrator adds an active technology. Please contact your BluJay team.</div>
    <?php else: ?>
    <?php if ($errors): ?><div class="alert danger" role="alert"><?= $e($errors['form'] ?? 'Please review the highlighted fields below.') ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="<?= $e($url('register')) ?>" class="form-stack registration-form" data-submit>
        <?= Ui::csrf() ?>
        <section class="registration-section" aria-labelledby="registration-personal">
            <h3 id="registration-personal">Personal information</h3>
            <div class="registration-fields">
                <div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" autocomplete="name" required minlength="2" maxlength="120" value="<?= $e($old['full_name'] ?? '') ?>" placeholder="Your full name"<?= Ui::invalid($errors,'full_name') ?>><?= Ui::error($errors,'full_name') ?></div>
                <div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" autocomplete="tel" required maxlength="24" value="<?= $e($old['phone'] ?? '') ?>" placeholder="+91 98765 43210"<?= Ui::invalid($errors,'phone') ?>><?= Ui::error($errors,'phone') ?><p class="field-hint">Use this number to sign in. Include your country code outside India.</p></div>
            </div>
        </section>
        <section class="registration-section" aria-labelledby="registration-professional">
            <h3 id="registration-professional">Professional information</h3>
            <div class="registration-fields">
                <?php $selectedTechnologyIds=$old['technology_ids']??[];$pickerId='registration-tech-stack';$pickerHint='Choose the technologies and skills you actively work with. Maximum 20.';require ROOT.'/app/Views/components/tech-stack-picker.php';?>
                <fieldset class="field"><legend>Candidate classification</legend><div class="radio-options"><?php foreach (['Genuine','Fake'] as $classification): ?><label class="radio-card"><input type="radio" name="classification" value="<?= $e($classification) ?>" required <?= ($old['classification'] ?? '') === $classification ? 'checked' : '' ?>><span><?= $e($classification) ?></span></label><?php endforeach; ?></div><?= Ui::error($errors,'classification') ?><p class="field-hint">Choose the classification that applies to your profile.</p></fieldset>
            </div>
        </section>
        <section class="registration-section" aria-labelledby="registration-documents">
            <h3 id="registration-documents">Documents</h3>
            <div class="registration-fields">
                <div class="field photo-upload" data-file-upload data-image-preview data-max-bytes="2097152"><label for="photo">Profile photo <span class="label-note">optional</span></label><label class="file-upload-control photo-upload-control" for="photo"><span class="photo-upload-preview" data-photo-preview><?= Ui::icon('user') ?></span><span class="file-upload-copy"><strong data-file-name>Upload profile photo</strong><small data-file-status>JPG or PNG · maximum 2 MB</small></span></label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png"<?= Ui::invalid($errors,'photo') ?>><?= Ui::error($errors,'photo') ?></div>
                <div class="field resume-upload" data-file-upload data-max-bytes="5242880"><label for="resume">Resume <span class="label-note">optional</span></label><label class="file-upload-control" for="resume"><span class="file-upload-icon"><?= Ui::icon('briefcase') ?></span><span class="file-upload-copy"><strong data-file-name>Upload resume</strong><small data-file-status>PDF or DOCX · maximum 5 MB</small></span></label><input id="resume" name="resume" type="file" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"<?= Ui::invalid($errors,'resume') ?>><?= Ui::error($errors,'resume') ?></div>
            </div>
        </section>
        <section class="registration-section" aria-labelledby="registration-security">
            <h3 id="registration-security">Security</h3>
            <div class="registration-fields"><div class="field"><label for="password">Create password</label><div class="password-wrap"><input id="password" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="72" placeholder="At least 12 characters"<?= Ui::invalid($errors,'password') ?>><button type="button" class="password-toggle" data-password="password" aria-controls="password" aria-pressed="false">Show</button></div><?= Ui::error($errors,'password') ?></div></div>
        </section>
        <button class="button primary full" type="submit">Create account <?= Ui::icon('arrow') ?></button>
    </form>
    <?php endif; ?>
    <p class="auth-switch">Already have an account? <a href="<?= $e($url('login')) ?>">Sign in <?= Ui::icon('arrow') ?></a></p>
</section>
