<?php use App\Support\Ui; ?>
<div class="home-head">
    <div><span class="eyebrow">PARTNER CONSULTANCY</span><h1><?= $e($consultancy['name']) ?></h1><p>Book interview support for your students and track every call.</p></div>
</div>
<div class="action-tiles">
    <a class="action-tile primary" href="<?= $e($url('consultancy/book')) ?>"><span class="action-tile-icon"><?= Ui::icon('plus') ?></span><span><strong>Book an interview</strong><small>Choose a student, company and live slot.</small></span></a>
    <a class="action-tile" href="<?= $e($url('consultancy/students/new')) ?>"><span class="action-tile-icon"><?= Ui::icon('user') ?></span><span><strong>Add a student</strong><small>Upload a resume and we fill in the skills.</small></span></a>
    <a class="action-tile" href="<?= $e($url('consultancy/reports')) ?>"><span class="action-tile-icon"><?= Ui::icon('chart') ?></span><span><strong>View reports</strong><small>Calls by month, student, company and technology.</small></span></a>
</div>
<section class="metric-strip card" aria-label="Your numbers"><?php foreach ([['Students', $studentCount, 'user'], ['Upcoming', $allTime['upcoming'] ?? 0, 'calendar'], ['Calls this month', $thisMonth['booked'] ?? 0, 'phone'], ['Completed', $allTime['completed'] ?? 0, 'check'], ['Cancelled', $allTime['cancelled'] ?? 0, 'close']] as [$label, $value, $icon]): ?><div><span><?= Ui::icon($icon) ?><?= $e($label) ?></span><strong><?= $e($value) ?></strong></div><?php endforeach; ?></section>
<section class="card home-list">
    <div class="card-heading"><div><h2>Upcoming interviews</h2><p>Next confirmed slots for your students (IST).</p></div><a class="text-link" href="<?= $e($url('consultancy/interviews')) ?>">All interviews <?= Ui::icon('arrow') ?></a></div>
    <?php if (!$upcoming): ?><div class="empty-state"><div class="empty-icon"><?= Ui::icon('calendar') ?></div><h3>No upcoming interviews</h3><p>Book a slot for one of your students to see it here.</p></div>
    <?php else: ?><div class="row-list"><?php foreach ($upcoming as $item): ?><a class="row-item" href="<?= $e($url('consultancy/interview?id=' . $item['id'])) ?>"><span class="date-tile"><strong><?= $e($item['start_local']->format('d')) ?></strong><?= $e(strtoupper($item['start_local']->format('M'))) ?></span><span class="row-copy"><strong><?= $e($item['candidate_name']) ?></strong><small><?= $e($item['start_local']->format('g:i A')) ?> · <?= $e($item['company_name']) ?> · <?= $e($item['technology_name']) ?></small></span><span class="candidate-location<?= empty($item['location_name']) ? ' pending' : '' ?>"><?= Ui::icon('pin') ?><?= !empty($item['location_name']) ? $e($item['location_name']) : 'TBC' ?></span><?= Ui::icon('chevron', 'row-chevron') ?></a><?php endforeach; ?></div><?php endif; ?>
</section>
