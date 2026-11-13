<?php use App\Support\Ui; $area = $area ?? 'candidate'; $forStudents = $area === 'consultancy'; $booked = $interview['status'] === 'BOOKED'; ?>
<a class="back-link" href="<?= $e($url($area . '/interviews')) ?>">← <?= $forStudents ? 'All interviews' : 'My interviews' ?></a>
<section class="card detail-head">
    <div class="detail-head-top">
        <div>
            <span class="badge <?= $interview['status'] === 'CANCELLED' ? 'danger-badge' : 'green' ?>"><?= $e(ucfirst(strtolower($interview['status']))) ?></span>
            <h1><?= $e($interview['company_name']) ?></h1>
            <p><?= $e($interview['job_role']) ?> · <?= $e($interview['technology_name']) ?></p>
            <?php if ($forStudents): ?><p class="student-name"><?= Ui::icon('user') ?>Student: <a href="<?= $e($url('consultancy/student?id=' . $interview['candidate_id'])) ?>"><?= $e($interview['candidate_name']) ?></a></p><?php endif; ?>
        </div>
        <span class="date-tile large"><strong><?= $e($interview['start_local']->format('d')) ?></strong><?= $e(strtoupper($interview['start_local']->format('M'))) ?></span>
    </div>
    <dl class="fact-grid">
        <div><dt><?= Ui::icon('calendar') ?>Date</dt><dd><?= $e($interview['display_date']) ?></dd></div>
        <div><dt><?= Ui::icon('clock') ?>Time</dt><dd><?= $e($interview['display_time']) ?> <small>(<?= $e($interview['duration_minutes']) ?> min)</small></dd></div>
        <?php if ($booked): ?><div class="fact-location<?= empty($interview['location_name']) ? ' pending' : '' ?>"><dt><?= Ui::icon('pin') ?>Location</dt><dd><?= !empty($interview['location_name']) ? $e($interview['location_name']) : 'To be confirmed' ?></dd></div><?php endif; ?>
        <div><dt><?= Ui::icon('monitor') ?>Type</dt><dd><?= $e(ucfirst(strtolower($interview['interview_type']))) ?></dd></div>
    </dl>
    <?php if ($booked && empty($interview['location_name'])): ?><p class="fact-note"><?= Ui::icon('pin') ?>BluJay assigns the office (KPHB or Ameerpet) the evening before your interview.</p><?php endif; ?>
    <div class="detail-actions">
    <?php if ($interview['status'] === 'CANCELLED'): ?>
        <p class="muted">This interview was cancelled and no longer reserves a resource or support person.</p>
    <?php elseif ($interview['can_change']): ?>
        <a class="button primary" href="<?= $e($url($area . '/interview/reschedule?id=' . $interview['id'])) ?>"><?= Ui::icon('edit') ?>Reschedule</a>
        <form method="post" action="<?= $e($url($area . '/interview/cancel')) ?>" data-confirm-form data-confirm-title="Cancel interview?" data-confirm-message="This releases the interview slot and cannot be undone by you." data-confirm-button="Cancel interview"><?= Ui::csrf() ?><input type="hidden" name="id" value="<?= $e($interview['id']) ?>"><button class="button secondary danger-button" type="submit"><?= Ui::icon('trash') ?>Cancel interview</button></form>
    <?php else: ?>
        <span class="cutoff-note"><?= Ui::icon('clock') ?>This interview is inside the change cutoff. Contact your BluJay administrator if you need help.</span>
    <?php endif; ?>
    </div>
</section>
<section class="card detail-more">
    <h2>Interview details</h2>
    <dl class="detail-list">
        <div><dt>Interview round</dt><dd><?= $e($interview['interview_round']) ?></dd></div>
        <div><dt>Technology</dt><dd><?= $e($interview['technology_name']) ?></dd></div>
        <div><dt>Support person</dt><dd><?= $interview['support_person_name'] ? $e($interview['support_person_name']) : '<span class="muted">Not assigned</span>' ?></dd></div>
        <div><dt>HR contact</dt><dd><?= $interview['hr_unavailable'] ? 'Details not available' : $e($interview['hr_name'] . ' · ' . $interview['hr_phone']) ?></dd></div>
        <div><dt>Booking reference</dt><dd>#<?= $e($interview['id']) ?></dd></div>
        <?php if ($booked && $interview['can_change']): ?><div><dt>Changes</dt><dd>Reschedule or cancel any time before the change cutoff.</dd></div><?php endif; ?>
    </dl>
</section>
