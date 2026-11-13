<?php use App\Support\Ui; use App\Repositories\CandidateTechnologyRepository as Stack; ?>
<div class="page-heading"><div><span class="eyebrow">YOUR STUDENTS</span><h1>Students</h1><p><?= count($students) ?> student<?= count($students) === 1 ? '' : 's' ?> managed by <?= $e($consultancy['name']) ?>.</p></div><a class="button primary" href="<?= $e($url('consultancy/students/new')) ?>"><?= Ui::icon('plus') ?>Add student</a></div>
<form class="card inline-search" method="get" role="search"><label class="visually-hidden" for="student-search">Search students</label><input id="student-search" type="search" name="search" value="<?= $e($search) ?>" placeholder="Search by name or phone" maxlength="80"><button class="button secondary" type="submit">Search</button><?php if ($search !== ''): ?><a class="text-link" href="<?= $e($url('consultancy/students')) ?>">Clear</a><?php endif; ?></form>
<?php if (!$students): ?>
<div class="empty-state card"><div class="empty-icon"><?= Ui::icon('user') ?></div><h3><?= $search !== '' ? 'No students match your search' : 'No students yet' ?></h3><p>Add each student once with their resume. Then book interviews in their name.</p><a class="button primary" href="<?= $e($url('consultancy/students/new')) ?>">Add student</a></div>
<?php else: ?>
<div class="student-grid"><?php foreach ($students as $student): ?>
<a class="card student-card" href="<?= $e($url('consultancy/student?id=' . $student['id'])) ?>">
    <div class="student-card-head"><strong><?= $e($student['full_name']) ?></strong><span class="badge neutral"><?= (int) $student['upcoming'] ?> upcoming</span></div>
    <p><?= $e($student['phone']) ?><?php if ($student['experience_years'] !== null): ?> · <?= $e(Stack::yearsText((float) $student['experience_years'])) ?> experience<?php endif; ?></p>
    <?php if ($student['tech_stack']): ?><span class="skill-chips compact"><?php foreach (array_slice($student['tech_stack'], 0, 4) as $skill): ?><span class="skill-chip"><?= $e(Stack::skillLabel($skill)) ?></span><?php endforeach; ?><?php if (count($student['tech_stack']) > 4): ?><span class="skill-more">+<?= count($student['tech_stack']) - 4 ?> more</span><?php endif; ?></span><?php endif; ?>
    <small><?= (int) $student['booked'] ?> interview<?= (int) $student['booked'] === 1 ? '' : 's' ?> booked</small>
</a>
<?php endforeach; ?></div>
<?php endif; ?>
