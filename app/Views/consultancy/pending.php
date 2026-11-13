<?php use App\Support\Ui; $status = $consultancy['status']; ?>
<div class="page-heading"><div><span class="eyebrow">PARTNER CONSULTANCY</span><h1><?= $e($consultancy['name']) ?></h1><p>Contact: <?= $e($consultancy['contact_name']) ?> · <?= $e($consultancy['phone']) ?></p></div></div>
<section class="card consultancy-status-card status-<?= $e(strtolower($status)) ?>">
    <span class="account-type-icon"><?= Ui::icon($status === 'PENDING' ? 'clock' : 'shield') ?></span>
    <?php if ($status === 'PENDING'): ?>
        <h2>Waiting for BluJay approval</h2>
        <p>Thanks for registering. Our team reviews new consultancies before they can book interview slots. You'll get access to students, bookings and reports here as soon as your account is approved.</p>
    <?php elseif ($status === 'REJECTED'): ?>
        <h2>Registration not approved</h2>
        <p>Your consultancy registration was not approved. Please contact the BluJay team if you think this is a mistake.</p>
    <?php else: ?>
        <h2>Account suspended</h2>
        <p>Bookings for this consultancy are paused. Please contact the BluJay team.</p>
    <?php endif; ?>
    <?php if (!empty($consultancy['review_note'])): ?><p class="review-note"><strong>Note from BluJay:</strong> <?= $e($consultancy['review_note']) ?></p><?php endif; ?>
    <p class="muted">Registered on <?= $e((new DateTimeImmutable($consultancy['created_at']))->format('d M Y')) ?></p>
</section>
