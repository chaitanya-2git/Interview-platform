<?php use App\Support\Ui; ?>
<div class="page-heading"><div><a class="back-link" href="<?= $e($url('consultancy/students')) ?>">← Students</a><span class="eyebrow">NEW STUDENT</span><h1>Add a student</h1><p>Add the student once. Every interview you book for them shows their own name to the BluJay team.</p></div></div>
<?php if (!$technologies): ?><div class="alert" role="status">Adding students opens when BluJay activates at least one technology.</div><?php else: ?>
<?php if ($errors): ?><div class="alert danger" role="alert"><?= $e($errors['form'] ?? 'Please review the highlighted fields below.') ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= $e($url('consultancy/students/new')) ?>" class="card form-stack registration-form student-form" data-submit<?php if (\App\Services\ResumeParserService::configured()): ?> data-resume-autofill="<?= $e($url('resume/parse')) ?>"<?php endif; ?>>
    <?= Ui::csrf() ?>
    <section class="registration-section">
        <h3>Resume &amp; photo</h3>
        <p class="section-note">Upload the student's resume first. We read it to fill in their name, skills and experience.</p>
        <div class="registration-fields">
            <div class="field resume-upload" data-file-upload data-max-bytes="5242880"><label for="resume">Resume</label><label class="file-upload-control" for="resume"><span class="file-upload-icon"><?= Ui::icon('briefcase') ?></span><span class="file-upload-copy"><strong data-file-name>Upload resume</strong><small data-file-status>PDF or DOCX · maximum 5 MB</small></span></label><input id="resume" name="resume" type="file" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"<?= Ui::invalid($errors,'resume') ?>><?= Ui::error($errors,'resume') ?><p class="resume-ai-status" data-resume-ai-status role="status" aria-live="polite" hidden></p></div>
            <div class="field photo-upload" data-file-upload data-image-preview data-max-bytes="2097152"><label for="photo">Photo <span class="label-note">optional</span></label><label class="file-upload-control photo-upload-control" for="photo"><span class="photo-upload-preview" data-photo-preview><?= Ui::icon('user') ?></span><span class="file-upload-copy"><strong data-file-name>Upload photo</strong><small data-file-status>JPG or PNG · maximum 2 MB</small></span></label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png"<?= Ui::invalid($errors,'photo') ?>><?= Ui::error($errors,'photo') ?></div>
        </div>
    </section>
    <section class="registration-section">
        <h3>Student details</h3>
        <div class="registration-fields">
            <div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" required minlength="2" maxlength="120" value="<?= $e($old['full_name'] ?? '') ?>" placeholder="Student's full name"<?= Ui::invalid($errors,'full_name') ?>><?= Ui::error($errors,'full_name') ?></div>
            <div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" required maxlength="24" value="<?= $e($old['phone'] ?? '') ?>" placeholder="+91 98765 43210"<?= Ui::invalid($errors,'phone') ?>><?= Ui::error($errors,'phone') ?><p class="field-hint">Each student needs their own number. Numbers already registered with BluJay can't be added.</p></div>
            <div class="field"><label for="experience_years">Total experience <span class="label-note">years</span></label><input id="experience_years" name="experience_years" type="number" inputmode="decimal" min="0" max="50" step="0.5" required value="<?= $e($old['experience_years'] ?? '') ?>" placeholder="e.g. 2 or 3.5"<?= Ui::invalid($errors,'experience_years') ?>><?= Ui::error($errors,'experience_years') ?></div>
            <?php $selectedTechnologyIds = $old['technology_ids'] ?? []; $selectedYears = $old['technology_years'] ?? []; $pickerId = 'student-tech-stack'; $pickerHint = 'Technologies the student works with, with years of experience for each. Maximum 20.'; require ROOT . '/app/Views/components/tech-stack-picker.php'; ?>
            <fieldset class="field"><legend>Classification</legend><div class="radio-options"><?php foreach (['Genuine', 'Fake'] as $classification): ?><label class="radio-card"><input type="radio" name="classification" value="<?= $e($classification) ?>" required <?= ($old['classification'] ?? '') === $classification ? 'checked' : '' ?>><span><?= $e($classification) ?></span></label><?php endforeach; ?></div><?= Ui::error($errors,'classification') ?></fieldset>
        </div>
    </section>
    <button class="button primary full" type="submit">Add student <?= Ui::icon('arrow') ?></button>
</form>
<script type="module" src="<?= $e($url('assets/resume-autofill.js')) ?>?v=<?= (int) (@filemtime(ROOT . '/public/assets/resume-autofill.js') ?: 0) ?>"></script>
<?php endif; ?>
