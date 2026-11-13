<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\{RateLimiter, ResumeParserService};
use App\Validation\ValidationException;

/** JSON endpoint used by the registration and profile forms to pre-fill details from a resume. */
final class ResumeParseController
{
    public function parse(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!ResumeParserService::configured()) { http_response_code(503); echo json_encode(['ok' => false, 'message' => 'Automatic resume reading is not available. Please fill in your details.']); return; }
        $limiter = new RateLimiter();
        $partner = ((new \App\Services\AuthService())->user()['role'] ?? '') === 'CONSULTANCY'; // consultancies add many students
        if (!$limiter->allow('resume-parse-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $partner ? 80 : 15, 3600) || !$limiter->allow('resume-parse-session', session_id(), $partner ? 60 : 6, 3600)) {
            http_response_code(429); echo json_encode(['ok' => false, 'message' => 'Too many resumes read in the last hour. Please fill in your details manually.']); return;
        }
        $file = isset($_FILES['resume']) && is_array($_FILES['resume']) ? $_FILES['resume'] : [];
        try {
            $result = (new ResumeParserService())->parse($file);
            echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE);
        } catch (ValidationException $error) {
            http_response_code(422); echo json_encode(['ok' => false, 'message' => $error->errors['resume'] ?? 'Please check the resume file.']);
        } catch (\Throwable $error) {
            error_log('Resume parse error: ' . $error->getMessage());
            http_response_code(502); echo json_encode(['ok' => false, 'message' => 'We could not read this resume automatically. Please fill in your details.']);
        }
    }
}
