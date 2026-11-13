<?php use App\Support\Ui; $labels = ['' => 'All', 'PENDING' => 'Pending', 'APPROVED' => 'Approved', 'SUSPENDED' => 'Suspended', 'REJECTED' => 'Rejected']; ?>
<div class="page-heading"><div><span class="eyebrow">PARTNERS</span><h1>Consultancies</h1><p>Approve partner consultancies and review the interviews they book for their students.</p></div></div>
<nav class="preset-bar" aria-label="Filter by status"><?php foreach ($labels as $key => $label): $count = $key === '' ? array_sum($counts) : ($counts[$key] ?? 0); ?><a class="<?= $status === $key ? 'active' : '' ?>" href="<?= $e($url('admin/consultancies' . ($key !== '' ? '?status=' . strtolower($key) : ''))) ?>" <?= $status === $key ? 'aria-current="page"' : '' ?>><?= $e($label) ?> <span class="count-pill<?= $key === 'PENDING' && $count > 0 ? ' attention' : '' ?>"><?= (int) $count ?></span></a><?php endforeach; ?></nav>
<?php if (!$consultancies): ?>
<div class="empty-state card"><div class="empty-icon"><?= Ui::icon('briefcase') ?></div><h3>No consultancies here</h3><p>Consultancies register with Google from the sign-in page and appear here for approval.</p></div>
<?php else: ?>
<div class="card table-card"><div class="table-scroll"><table>
<thead><tr><th scope="col">Consultancy</th><th scope="col">Contact</th><th scope="col">Status</th><th scope="col">Students</th><th scope="col">Upcoming</th><th scope="col">Booked</th><th scope="col">Registered</th></tr></thead>
<tbody><?php foreach ($consultancies as $c): ?>
<tr>
    <td><a class="table-link" href="<?= $e($url('admin/consultancy?id=' . $c['id'])) ?>"><strong><?= $e($c['name']) ?></strong></a><?php if ($c['city']): ?><br><small><?= $e($c['city']) ?></small><?php endif; ?></td>
    <td><?= $e($c['contact_name']) ?><br><small><?= $e($c['phone']) ?> · <?= $e($c['owner_email']) ?></small></td>
    <td><span class="badge consultancy-status <?= $e(strtolower($c['status'])) ?>"><?= $e(ucfirst(strtolower($c['status']))) ?></span></td>
    <td><?= (int) $c['students'] ?></td><td><?= (int) $c['upcoming'] ?></td><td><?= (int) $c['booked'] ?></td>
    <td><?= $e((new DateTimeImmutable($c['created_at']))->format('d M Y')) ?></td>
</tr>
<?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>
