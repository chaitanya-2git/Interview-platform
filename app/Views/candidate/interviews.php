<?php use App\Support\Ui; $area = $area ?? 'candidate'; $forStudents = $area === 'consultancy'; ?>
<div class="page-heading list-heading"><div><span class="eyebrow"><?= $forStudents ? 'STUDENT SCHEDULE' : 'YOUR SCHEDULE' ?></span><h1><?= $forStudents ? 'Student interviews' : 'My interviews' ?></h1><p>Upcoming, completed and cancelled interviews in one place.</p></div><a class="button primary heading-book" href="<?= $e($url($area . '/book')) ?>"><?= Ui::icon('plus') ?>Book interview</a></div>
<div class="interview-tabs segmented" role="tablist" aria-label="Interview status"><button class="active" type="button" role="tab" data-tab="upcoming" aria-selected="true">Upcoming <span><?= count($upcoming) ?></span></button><button type="button" role="tab" data-tab="past" aria-selected="false">Past <span><?= count($past) ?></span></button><button type="button" role="tab" data-tab="cancelled" aria-selected="false">Cancelled <span><?= count($cancelled) ?></span></button></div>
<?php foreach (['upcoming' => $upcoming, 'past' => $past, 'cancelled' => $cancelled] as $section => $items): ?><section class="interview-tab-panel <?= $section === 'upcoming' ? 'active' : '' ?>" data-panel="<?= $e($section) ?>" <?= $section === 'upcoming' ? '' : 'hidden' ?>>
<?php if (!$items): ?><div class="empty-state card"><div class="empty-icon"><?= Ui::icon($section === 'cancelled' ? 'trash' : 'calendar') ?></div><h3>No <?= $e($section) ?> interviews</h3><p><?= $section === 'upcoming' ? 'When you confirm an interview, it will appear here.' : 'There are no interview records in this section.' ?></p><?php if ($section === 'upcoming'): ?><a class="button primary" href="<?= $e($url($area . '/book')) ?>">Book interview</a><?php endif; ?></div>
<?php else: ?><div class="iv-list"><?php foreach ($items as $item): $detail = $url($area . '/interview?id=' . $item['id']); ?>
<article class="iv-card card<?= $item['status'] === 'CANCELLED' ? ' cancelled' : '' ?>">
    <a class="iv-main" href="<?= $e($detail) ?>">
        <span class="date-tile"><strong><?= $e($item['start_local']->format('d')) ?></strong><?= $e(strtoupper($item['start_local']->format('M'))) ?></span>
        <span class="iv-copy">
            <?php if ($forStudents): ?><span class="student-name"><?= Ui::icon('user') ?><?= $e($item['candidate_name']) ?></span><?php endif; ?>
            <strong class="iv-title"><?= $e($item['company_name']) ?></strong>
            <small><?= $e($item['job_role']) ?> · <?= $e($item['technology_name']) ?></small>
            <span class="iv-meta"><span><?= Ui::icon('clock') ?><?= $e($item['start_local']->format('D')) ?>, <?= $e($item['display_time']) ?></span><?php if ($item['support_person_name']): ?><span><?= Ui::icon('shield') ?><?= $e($item['support_person_name']) ?></span><?php endif; ?></span>
            <span class="iv-chips"><span class="badge <?= $item['interview_type'] === 'TOOL' ? 'purple' : 'neutral' ?>"><?= $e(ucfirst(strtolower($item['interview_type']))) ?> · <?= $e($item['duration_minutes']) ?> min</span><?php if ($item['status'] === 'CANCELLED'): ?><span class="badge danger-badge">Cancelled</span><?php endif; ?><?php if ($item['status'] === 'BOOKED'): ?><span class="candidate-location<?= empty($item['location_name']) ? ' pending' : '' ?>"><?= Ui::icon('pin') ?><?= !empty($item['location_name']) ? $e($item['location_name']) : 'Location to be confirmed' ?></span><?php endif; ?></span>
        </span>
        <?= Ui::icon('chevron', 'row-chevron') ?>
    </a>
    <?php if ($section === 'upcoming' && $item['can_change']): ?><div class="iv-actions"><a class="button secondary" href="<?= $e($url($area . '/interview/reschedule?id=' . $item['id'])) ?>"><?= Ui::icon('edit') ?>Reschedule</a><a class="button secondary" href="<?= $e($detail) ?>">Details</a></div><?php endif; ?>
</article>
<?php endforeach; ?></div><?php endif; ?></section><?php endforeach; ?>
