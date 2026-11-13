<?php
/** Floating help assistant, included by every layout. Expects $user (nullable). */
use App\Support\{Ui, Session};
use App\Services\HelpBotService;
$helpAudience = HelpBotService::audienceFor($user ?? null);
$helpFaqs = (new HelpBotService())->faqs($helpAudience);
// Put the question that matches the current page first (e.g. "I forgot my password" on the reset page).
$helpPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$helpFocus = ['forgot-password' => 'forgot', 'reset-password' => 'forgot', 'register' => 'create a new account', 'book' => 'book', 'profile' => 'skills', 'students' => 'add a student', 'locations' => 'assign locations', 'reports' => 'reports'];
foreach ($helpFocus as $fragment => $needle) {
    if (!str_contains($helpPath, $fragment)) continue;
    usort($helpFaqs, fn ($a, $b) => (int) str_contains(strtolower($b['q']), $needle) <=> (int) str_contains(strtolower($a['q']), $needle));
    break;
}
$helpData = ['audience' => $helpAudience, 'name' => isset($user['full_name']) ? explode(' ', (string) $user['full_name'])[0] : '', 'faqs' => $helpFaqs, 'base' => $url(''), 'endpoint' => $url('help/ask')];
?>
<div class="helpbot" data-helpbot data-token="<?= $e(Session::token()) ?>">
    <button class="helpbot-launcher" type="button" aria-expanded="false" aria-controls="helpbot-panel" data-helpbot-toggle><?= Ui::icon('chat') ?><span>Help</span></button>
    <div class="helpbot-nudge" data-helpbot-nudge hidden><button type="button" data-helpbot-toggle>New here? <strong>Ask me how things work</strong></button><button type="button" class="helpbot-nudge-close" data-helpbot-nudge-close aria-label="Hide tip"><?= Ui::icon('close') ?></button></div>
    <section class="helpbot-panel" id="helpbot-panel" role="dialog" aria-modal="false" aria-labelledby="helpbot-title" hidden>
        <header class="helpbot-header">
            <span class="helpbot-avatar"><img src="<?= $e($url('assets/brand/blujay-mark-192.png')) ?>" alt="" width="32" height="32"></span>
            <span class="helpbot-title"><strong id="helpbot-title">BluJay Help</strong><small><span class="helpbot-online"></span>Ask anything about this app</small></span>
            <button class="helpbot-icon" type="button" data-helpbot-reset aria-label="Start a new chat" title="New chat"><?= Ui::icon('edit') ?></button>
            <button class="helpbot-icon" type="button" data-helpbot-toggle aria-label="Close help"><?= Ui::icon('close') ?></button>
        </header>
        <div class="helpbot-messages" data-helpbot-messages aria-live="polite"></div>
        <div class="helpbot-suggest"><p>Common questions</p><div class="helpbot-chips" data-helpbot-chips></div></div>
        <form class="helpbot-form" data-helpbot-form>
            <label class="visually-hidden" for="helpbot-input">Type your question</label>
            <input id="helpbot-input" type="text" maxlength="500" autocomplete="off" placeholder="Type your question… (English or Telugu)" data-helpbot-input>
            <button type="submit" aria-label="Send question"><?= Ui::icon('send') ?></button>
        </form>
        <p class="helpbot-note"><?= Ui::icon('shield') ?>Never share your password or OTP code here.</p>
    </section>
    <script type="application/json" id="helpbot-data"><?= json_encode($helpData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
</div>
<script src="<?= $e($url('assets/help-bot.js')) ?>?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/assets/help-bot.js') ?: 0) ?>" defer></script>
