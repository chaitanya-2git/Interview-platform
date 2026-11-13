<?php use App\Support\Ui; $t = $report['totals']; $hours = intdiv((int) ($t['minutes'] ?? 0), 60); $minutes = (int) ($t['minutes'] ?? 0) % 60; ?>
<div class="page-heading"><div><span class="eyebrow">YOUR ACTIVITY</span><h1>Reports</h1><p><?= $e((new DateTimeImmutable($from))->format('d M Y')) ?> – <?= $e((new DateTimeImmutable($to))->format('d M Y')) ?> · Asia/Kolkata</p></div></div>
<form class="report-filters card" method="get">
    <div class="field"><label for="report-from">From</label><input id="report-from" type="date" name="from" value="<?= $e($from) ?>" required></div>
    <div class="field"><label for="report-to">To</label><input id="report-to" type="date" name="to" value="<?= $e($to) ?>" required></div>
    <button class="button primary" type="submit">Apply</button>
</form>
<section class="ops-metrics" aria-label="Totals"><?php foreach ([['Calls booked', $t['booked'] ?? 0, 'phone'], ['Completed', $t['completed'] ?? 0, 'check'], ['Upcoming', $t['upcoming'] ?? 0, 'calendar'], ['Cancelled', $t['cancelled'] ?? 0, 'close'], ['Students with calls', $t['students'] ?? 0, 'user'], ['Tool / Normal', ($t['tool'] ?? 0) . ' / ' . ($t['normal'] ?? 0), 'monitor'], ['Support time', $hours . 'h ' . $minutes . 'm', 'clock']] as [$label, $value, $icon]): ?><article><span><?= Ui::icon($icon) ?><?= $e($label) ?></span><strong><?= $e($value) ?></strong></article><?php endforeach; ?></section>
<div class="report-grid">
<?php foreach (['by_month' => ['By month', 'Month'], 'by_student' => ['By student', 'Student'], 'by_company' => ['By company', 'Company'], 'by_technology' => ['By technology', 'Technology']] as $key => [$heading, $column]): ?>
<section class="card"><div class="card-heading"><div><h2><?= $e($heading) ?></h2></div></div>
<?php if (!$report[$key]): ?><p class="muted">No interviews in this period.</p><?php else: ?>
<div class="table-scroll"><table><thead><tr><th scope="col"><?= $e($column) ?></th><th scope="col">Booked</th><th scope="col">Completed</th><th scope="col">Cancelled</th></tr></thead><tbody>
<?php foreach ($report[$key] as $row): $label = $key === 'by_month' ? (DateTimeImmutable::createFromFormat('!Y-m', $row['label']) ?: null)?->format('M Y') ?? $row['label'] : $row['label']; ?><tr><td><?= $e($label ?? '—') ?></td><td><?= (int) $row['booked'] ?></td><td><?= (int) $row['completed'] ?></td><td><?= (int) $row['cancelled'] ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
</section>
<?php endforeach; ?>
</div>
