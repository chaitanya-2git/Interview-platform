<?php use App\Support\Ui; use App\Repositories\CandidateTechnologyRepository as Stack; ?>
<div class="page-heading booking-heading"><div><span class="eyebrow">PLAN WITH CONFIDENCE</span><h1>Book an interview</h1><p>Who is this interview for?</p></div><a class="button secondary" href="<?= $e($url('consultancy/students/new')) ?>"><?= Ui::icon('plus') ?>New student</a></div>
<?php if (!$students): ?>
<div class="empty-state card"><div class="empty-icon"><?= Ui::icon('user') ?></div><h3>Add a student first</h3><p>Interviews are booked in each student's own name, so the BluJay team always knows who is attending.</p><a class="button primary" href="<?= $e($url('consultancy/students/new')) ?>">Add student</a></div>
<?php else: ?>
<form class="card choose-student" method="get" action="<?= $e($url('consultancy/book')) ?>">
    <div class="field"><label for="student-filter">Find a student</label><input id="student-filter" type="search" placeholder="Type a name or phone" data-student-filter autocomplete="off"></div>
    <div class="student-options" role="radiogroup" aria-label="Student">
    <?php foreach ($students as $index => $student): ?>
        <label class="student-option" data-student-option data-search="<?= $e(mb_strtolower($student['full_name'] . ' ' . $student['phone'])) ?>">
            <input type="radio" name="student_id" value="<?= $e($student['id']) ?>" required <?= $index === 0 && count($students) === 1 ? 'checked' : '' ?>>
            <span><strong><?= $e($student['full_name']) ?></strong><small><?= $e($student['phone']) ?> · <?= $e($student['tech_stack_label']) ?></small></span>
            <?php if ((int) $student['upcoming'] > 0): ?><span class="badge neutral"><?= (int) $student['upcoming'] ?> upcoming</span><?php endif; ?>
        </label>
    <?php endforeach; ?>
    </div>
    <button class="button primary full" type="submit">Continue <?= Ui::icon('arrow') ?></button>
</form>
<script src="<?= $e($url('assets/consultancy.js')) ?>" defer></script>
<?php endif; ?>
