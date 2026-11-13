<?php use App\Support\Ui; $dateKey = $date->format('Y-m-d'); $unassigned = count(array_filter($rows, static fn($r) => $r['location_id'] === null)); ?>
<div class="page-heading"><div><span class="eyebrow">NIGHTLY PLANNING</span><h1>Assign locations</h1><p>Choose where each candidate should go. Candidates and consultancies see the location on their interview.</p></div></div>
<nav class="location-days card" aria-label="Choose a date">
    <a class="button secondary" href="<?= $e($url('admin/locations?date=' . $date->modify('-1 day')->format('Y-m-d'))) ?>" aria-label="Previous day">←</a>
    <form method="get" class="location-date-form"><label class="visually-hidden" for="location-date">Date</label><input id="location-date" type="date" name="date" value="<?= $e($dateKey) ?>"><button class="button secondary" type="submit">Go</button></form>
    <a class="button secondary" href="<?= $e($url('admin/locations?date=' . $date->modify('+1 day')->format('Y-m-d'))) ?>" aria-label="Next day">→</a>
    <?php if ($pending): ?><div class="pending-days"><span><?= (int) $pendingTotal ?> upcoming to assign:</span><?php foreach (array_slice($pending, 0, 7) as $day): $d = new DateTimeImmutable($day['date']); ?><a class="<?= $day['date'] === $dateKey ? 'active' : '' ?>" href="<?= $e($url('admin/locations?date=' . $day['date'])) ?>"><?= $e($d->format('D d M')) ?> <span class="count-pill attention"><?= (int) $day['total'] ?></span></a><?php endforeach; ?></div><?php else: ?><div class="pending-days done"><?= Ui::icon('check') ?> All upcoming interviews have a location.</div><?php endif; ?>
</nav>
<section class="card"><div class="card-heading"><div><h2><?= $e($date->format('l, d F Y')) ?></h2><p><?= count($rows) ?> booked interview<?= count($rows) === 1 ? '' : 's' ?><?= $unassigned ? ' · ' . $unassigned . ' to be assigned' : '' ?></p></div></div>
<?php if (!$rows): ?><div class="empty-state"><div class="empty-icon"><?= Ui::icon('calendar') ?></div><h3>No interviews booked for this day</h3></div>
<?php elseif (!$locations): ?><div class="alert">No active locations are configured.</div>
<?php else: ?><div class="location-list"><?php foreach ($rows as $item): ?>
<article class="location-row<?= $item['location_id'] === null ? ' needs-location' : '' ?>" id="interview-<?= $e($item['id']) ?>">
    <time><?= $e($item['start_local']->format('g:i A')) ?><small><?= (int) $item['duration_minutes'] ?> min</small></time>
    <div class="location-row-copy"><strong><a href="<?= $e($url('admin/interview?id=' . $item['id'])) ?>"><?= $e($item['candidate_name']) ?></a><?= Ui::partnerBadge($item) ?><?= Ui::locationBadge($item) ?></strong><small><?= $e($item['company_name']) ?> · <?= $e($item['technology_name']) ?> · <?= $e(ucfirst(strtolower($item['interview_type']))) ?><?= $item['candidate_phone'] ? ' · ' . $e($item['candidate_phone']) : '' ?></small></div>
    <div class="location-choices" role="group" aria-label="Location for <?= $e($item['candidate_name']) ?>">
    <?php foreach ($locations as $location): $current = (int) $item['location_id'] === (int) $location['id']; ?>
        <form method="post" action="<?= $e($url('admin/interview/location')) ?>"><?= Ui::csrf() ?><input type="hidden" name="id" value="<?= $e($item['id']) ?>"><input type="hidden" name="location_id" value="<?= $e($location['id']) ?>"><input type="hidden" name="return" value="admin/locations?date=<?= $e($dateKey) ?>#interview-<?= $e($item['id']) ?>"><button type="submit" class="location-choice<?= $current ? ' selected' : '' ?>" <?= $current ? 'aria-pressed="true" disabled' : 'aria-pressed="false"' ?>><?= $e($location['name']) ?></button></form>
    <?php endforeach; ?>
    </div>
</article>
<?php endforeach; ?></div><?php endif; ?>
</section>
