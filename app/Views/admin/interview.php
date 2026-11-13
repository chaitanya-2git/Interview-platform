<?php use App\Support\Ui; $booked = $interview['status'] === 'BOOKED';
$details = [['Candidate', $interview['candidate_name']], ['Candidate phone', $interview['candidate_phone'] ?? 'Removed with candidate account'], ['Job role', $interview['job_role']], ['Technology', $interview['technology_name']], ['Interview round', $interview['interview_round']], ['Interview type', ucfirst(strtolower($interview['interview_type'])) . ' · ' . $interview['duration_minutes'] . ' min'], ['Assigned resource', $interview['resource_name']], ['Support person', $interview['support_person_name'] ?? 'Not required'], ['HR details', $interview['hr_unavailable'] ? 'Not available' : $interview['hr_name'] . ' · ' . $interview['hr_phone']], ['Booking reference', '#' . $interview['id']]]; ?>
<a class="back-link" href="<?= $e($url('admin/interviews')) ?>">← Interviews</a>
<section class="card detail-head">
    <div class="detail-head-top">
        <div>
            <span class="badge <?= $booked ? 'green' : 'danger-badge' ?>"><?= $e(ucfirst(strtolower($interview['status']))) ?></span>
            <h1><?= $e($interview['company_name']) ?></h1>
            <p class="detail-candidate"><?= Ui::candidateAvatar($interview, 'agenda-avatar') ?><span><strong><?= $e($interview['candidate_name']) ?></strong><?= Ui::partnerBadge($interview) ?><?php if ($interview['candidate_deleted']): ?> <span class="deleted-label">Deleted candidate</span><?php endif; ?></span></p>
        </div>
        <span class="date-tile large"><strong><?= $e($interview['start_local']->format('d')) ?></strong><?= $e(strtoupper($interview['start_local']->format('M'))) ?></span>
    </div>
    <dl class="fact-grid">
        <div><dt><?= Ui::icon('calendar') ?>Date</dt><dd><?= $e($interview['display_date']) ?></dd></div>
        <div><dt><?= Ui::icon('clock') ?>Time</dt><dd><?= $e($interview['display_time']) ?> <small>(<?= $e($interview['duration_minutes']) ?> min)</small></dd></div>
        <div class="fact-location<?= $booked && empty($interview['location_name']) ? ' pending' : '' ?>"><dt><?= Ui::icon('pin') ?>Location</dt><dd><?= $e($interview['location_name'] ?? ($booked ? 'To be assigned' : '—')) ?></dd></div>
        <div><dt><?= Ui::icon('monitor') ?>Resource</dt><dd><?= $e($interview['resource_name']) ?></dd></div>
    </dl>
    <?php if ($booked): ?>
    <div class="admin-controls">
        <?php if ($locations): ?><form method="post" action="<?= $e($url('admin/interview/location')) ?>" class="location-assign-form"><?= Ui::csrf() ?><input type="hidden" name="id" value="<?= $e($interview['id']) ?>"><input type="hidden" name="return" value="admin/interview?id=<?= $e($interview['id']) ?>"><label for="location_id">Interview location</label><div class="location-assign-row"><select id="location_id" name="location_id"><option value="">To be assigned</option><?php foreach ($locations as $location): ?><option value="<?= $e($location['id']) ?>" <?= (int) $interview['location_id'] === (int) $location['id'] ? 'selected' : '' ?>><?= $e($location['name']) ?></option><?php endforeach; ?></select><button class="button primary" type="submit">Save</button></div></form><?php endif; ?>
        <div class="detail-actions">
            <?php if (!$interview['candidate_deleted']): ?><a class="button secondary" href="<?= $e($url('admin/interview/reschedule?id=' . $interview['id'])) ?>"><?= Ui::icon('edit') ?>Reschedule</a><?php endif; ?>
            <form method="post" action="<?= $e($url('admin/interview/cancel')) ?>" data-confirm="Cancel this interview and release its assignment?"><?= Ui::csrf() ?><input type="hidden" name="id" value="<?= $e($interview['id']) ?>"><button class="button secondary danger-button" type="submit"><?= Ui::icon('trash') ?>Cancel interview</button></form>
        </div>
    </div>
    <p class="fact-note"><?= Ui::icon('shield') ?>Admins may act inside candidate time cutoffs. Conflicts and business hours are always enforced.</p>
    <?php endif; ?>
</section>
<section class="card detail-more">
    <h2>Booking details</h2>
    <dl class="detail-list"><?php foreach ($details as [$label, $value]): ?><div><dt><?= $e($label) ?></dt><dd><?= $e($value) ?></dd></div><?php endforeach; ?></dl>
</section>
