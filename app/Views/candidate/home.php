<?php
use App\Support\Ui;
use App\Repositories\CandidateTechnologyRepository as Stack;
$firstName = explode(' ', $user['full_name'])[0];
$later = $nextInterview ? array_slice($upcoming, 1) : $upcoming;
$today = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
?>
<div class="home-head">
    <div><span class="eyebrow"><?= $e(strtoupper($today->format('l, d M'))) ?></span><h1>Hello, <?= $e($firstName) ?></h1><p><?= $upcoming ? count($upcoming) . ' upcoming interview' . (count($upcoming) === 1 ? '' : 's') . ' on your schedule.' : 'You have no interviews booked yet.' ?></p></div>
</div>
<div class="home-grid">
<div class="home-main">
<?php if ($nextInterview): ?>
<section class="next-interview-card" aria-label="Next interview">
    <div class="next-interview-main">
        <span class="next-label"><?= Ui::icon('calendar') ?>Next interview</span>
        <h2><?= $e($nextInterview['company_name']) ?></h2>
        <p class="next-role"><?= $e($nextInterview['job_role']) ?> · <?= $e($nextInterview['technology_name']) ?></p>
        <dl class="next-facts">
            <div><dt><?= Ui::icon('calendar') ?>Date</dt><dd><?= $e($nextInterview['start_local']->format('D, d M')) ?></dd></div>
            <div><dt><?= Ui::icon('clock') ?>Time</dt><dd><?= $e($nextInterview['display_time']) ?></dd></div>
            <?php if ($nextInterview['status'] === 'BOOKED'): ?><div><dt><?= Ui::icon('pin') ?>Location</dt><dd class="<?= empty($nextInterview['location_name']) ? 'pending' : '' ?>"><?= !empty($nextInterview['location_name']) ? $e($nextInterview['location_name']) : 'To be confirmed' ?></dd></div><?php endif; ?>
            <div><dt><?= Ui::icon('monitor') ?>Type</dt><dd><?= $e(ucfirst(strtolower($nextInterview['interview_type']))) ?> · <?= $e($nextInterview['duration_minutes']) ?> min</dd></div>
        </dl>
        <?php if ($nextInterview['support_person_name']): ?><p class="next-support"><?= Ui::icon('shield') ?>Support person: <strong><?= $e($nextInterview['support_person_name']) ?></strong></p><?php endif; ?>
    </div>
    <div class="next-actions"><a class="button primary" href="<?= $e($url('candidate/interview?id=' . $nextInterview['id'])) ?>">View details <?= Ui::icon('arrow') ?></a><?php if ($nextInterview['can_change']): ?><a class="button secondary" href="<?= $e($url('candidate/interview/reschedule?id=' . $nextInterview['id'])) ?>"><?= Ui::icon('edit') ?>Reschedule</a><?php endif; ?></div>
</section>
<?php else: ?>
<section class="home-empty card"><div class="empty-icon"><?= Ui::icon('calendar') ?></div><div><h2>Book your first interview</h2><p>Tell us the company and role, pick a live slot, and a BluJay support person joins you.</p></div><a class="button primary" href="<?= $e($url('candidate/book')) ?>"><?= Ui::icon('plus') ?>Book an interview</a></section>
<?php endif; ?>

<section class="card home-list">
    <div class="card-heading"><div><h2><?= $nextInterview ? 'Also coming up' : 'Upcoming interviews' ?></h2></div><a class="text-link" href="<?= $e($url('candidate/interviews')) ?>">All interviews <?= Ui::icon('arrow') ?></a></div>
    <?php if ($later): ?>
    <div class="row-list"><?php foreach ($later as $item): ?><a class="row-item" href="<?= $e($url('candidate/interview?id=' . $item['id'])) ?>"><span class="date-tile"><strong><?= $e($item['start_local']->format('d')) ?></strong><?= $e(strtoupper($item['start_local']->format('M'))) ?></span><span class="row-copy"><strong><?= $e($item['company_name']) ?></strong><small><?= $e($item['start_local']->format('g:i A')) ?> · <?= $e($item['job_role']) ?></small></span><span class="candidate-location<?= empty($item['location_name']) ? ' pending' : '' ?>"><?= Ui::icon('pin') ?><?= !empty($item['location_name']) ? $e($item['location_name']) : 'TBC' ?></span><?= Ui::icon('chevron', 'row-chevron') ?></a><?php endforeach; ?></div>
    <?php else: ?><p class="muted row-empty"><?= $nextInterview ? 'Nothing else booked after your next interview.' : 'When you confirm an interview, it will appear here.' ?></p><?php endif; ?>
    <a class="row-add" href="<?= $e($url('candidate/book')) ?>"><?= Ui::icon('plus') ?>Book another interview</a>
</section>
</div>

<aside class="home-side">
    <section class="card side-profile">
        <div class="side-profile-head"><?= Ui::avatar($user, $profile, 'avatar-lg') ?><div><strong><?= $e($user['full_name']) ?></strong><small><?= $e($user['phone'] ?? '') ?></small></div></div>
        <?php if (!empty($profile['tech_stack'])): ?><div class="skill-chips"><?php foreach (array_slice($profile['tech_stack'], 0, 6) as $skill): ?><span class="skill-chip"><?= $e(Stack::skillLabel($skill)) ?></span><?php endforeach; ?></div><?php else: ?><p class="muted">Add your tech stack so support staff are matched to you.</p><?php endif; ?>
        <a class="button secondary side-button" href="<?= $e($url('profile')) ?>"><?= Ui::icon('user') ?>View profile</a>
    </section>
    <section class="card side-steps">
        <h2>How it works</h2>
        <ol>
            <li><span>1</span><div><strong>Book a slot</strong><small>Company, role and a live time that suits you.</small></div></li>
            <li><span>2</span><div><strong>Location confirmed</strong><small>We assign KPHB or Ameerpet the evening before.</small></div></li>
            <li><span>3</span><div><strong>Attend with support</strong><small>Your support person joins at the booked time.</small></div></li>
        </ol>
    </section>
    <?php if ($past): ?><section class="card side-recent"><div class="card-heading"><div><h2>Recent</h2></div></div><div class="row-list compact"><?php foreach ($past as $item): ?><a class="row-item" href="<?= $e($url('candidate/interview?id=' . $item['id'])) ?>"><span class="row-copy"><strong><?= $e($item['company_name']) ?></strong><small><?= $e($item['start_local']->format('d M · g:i A')) ?></small></span><?= Ui::icon('chevron', 'row-chevron') ?></a><?php endforeach; ?></div></section><?php endif; ?>
</aside>
</div>
