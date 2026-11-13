<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\{AuthService, HelpBotService, RateLimiter};

/** JSON endpoint behind the help assistant shown on every page (signed in or not). */
final class HelpBotController
{
    public function ask(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $question = is_string($_POST['question'] ?? null) ? trim($_POST['question']) : '';
        if ($question === '' || mb_strlen($question) > 500) { http_response_code(422); echo json_encode(['ok' => false, 'message' => 'Please type a question (up to 500 characters).']); return; }
        $limiter = new RateLimiter();
        if (!$limiter->allow('help-bot-session', session_id(), 30, 3600) || !$limiter->allow('help-bot-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 120, 3600)) {
            http_response_code(429); echo json_encode(['ok' => false, 'message' => 'You have asked a lot of questions in the last hour. Please try the ready questions below or try again later.']); return;
        }
        $history = json_decode(is_string($_POST['history'] ?? null) ? $_POST['history'] : '[]', true);
        $audience = HelpBotService::audienceFor((new AuthService())->user());
        echo json_encode(['ok' => true] + (new HelpBotService())->ask($question, $audience, is_array($history) ? $history : []), JSON_UNESCAPED_UNICODE);
    }
}
