<?php
declare(strict_types=1);
namespace App\Services;

use App\Support\{Config, Database};
use RuntimeException;

/**
 * BluJay help assistant: ready answers for common questions plus Gemini for anything typed freely.
 * Answers may carry navigation links, limited to pages the current visitor can open.
 */
final class HelpBotService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** Pages each audience may be sent to (path => button label). */
    private const PAGES = [
        'guest' => ['login' => 'Sign in', 'register' => 'Create account', 'forgot-password' => 'Forgot password'],
        'candidate' => ['candidate' => 'Home', 'candidate/book' => 'Book interview', 'candidate/interviews' => 'My interviews', 'profile' => 'My profile'],
        'consultancy' => ['consultancy' => 'Home', 'consultancy/book' => 'Book interview', 'consultancy/interviews' => 'Interviews', 'consultancy/students' => 'Students', 'consultancy/students/new' => 'Add a student', 'consultancy/reports' => 'Reports'],
        'admin' => ['admin' => 'Dashboard', 'admin/calendar' => 'Calendar', 'admin/interviews' => 'Interviews', 'admin/interview/new' => 'New interview', 'admin/locations' => 'Assign locations', 'admin/candidates' => 'Candidates', 'admin/consultancies' => 'Consultancies', 'admin/reports' => 'Reports', 'admin/audit' => 'Audit log', 'admin/resources' => 'Resources', 'admin/support' => 'Support team', 'admin/technologies' => 'Technologies', 'admin/settings' => 'Settings'],
    ];

    public static function configured(): bool { return Config::get('GEMINI_API_KEY') !== ''; }

    public static function audienceFor(?array $user): string
    {
        return match ($user['role'] ?? null) { 'ADMIN' => 'admin', 'CONSULTANCY' => 'consultancy', 'CANDIDATE' => 'candidate', default => 'guest' };
    }

    /** Ready questions shown as buttons, each with an instant answer. */
    public function faqs(string $audience): array
    {
        $rules = $this->rules();
        $all = [
            'guest' => [
                ['How do I create a new account?', "1. On the sign-in page tap \"Create an account\".\n2. Upload your resume (PDF or DOCX, up to 5 MB). We read it and fill in your name, experience and skills for you. Check them.\n3. Upload a clear profile photo (JPG or PNG). Resume and photo are both required.\n4. Enter your full name, 10-digit mobile number and total experience in years.\n5. Pick your tech stack and years for each skill, choose your classification, create a password and tap Create account.\nTip: you can also tap \"Continue with Google\" and fill the same details once.", ['register']],
                ['How do I sign in?', "1. Open the sign-in page.\n2. Enter the mobile number you registered with (admins use their email or username).\n3. Enter your password and tap Sign in.\nIf you joined with Google, just tap \"Continue with Google\".", ['login']],
                ['I forgot my password', "1. On the sign-in page tap \"Forgot password?\".\n2. Enter your registered mobile number and tap Send verification code.\n3. Type the 6-digit code you receive by SMS and tap Verify code (use Resend code if it does not come).\n4. Set a new password and sign in with it.\nIf the code does not arrive, wait a minute and try again, or contact your BluJay coordinator.", ['forgot-password']],
                ['Can I sign in with Google?', "Yes. Tap \"Continue with Google\" on the sign-in page.\n- Already registered with that Google account: you go straight to your dashboard.\n- First time: choose \"I'm a candidate\" (or \"I'm a consultancy\"), fill in your details once, and you are in.\nIf you already have a phone + password account, sign in with your password once after Google to connect them.", ['login']],
                ['Why is resume and photo required?', "Your BluJay support person uses your resume and photo to prepare for your interview and to recognise you on the day. We also read your resume automatically to fill in your skills and experience, so registration is faster.", ['register']],
                ['My phone number is already registered', "That number already has an account. Use \"Forgot password?\" to set a new password. If your consultancy added you, your consultancy books interviews for you, so contact them. Still stuck? Contact your BluJay coordinator.", ['forgot-password', 'login']],
                ['I am a consultancy. How do I join?', "1. On the sign-in page tap \"Continue with Google\" with your company Google account.\n2. Choose \"I'm a consultancy\".\n3. Enter the consultancy name, contact person, phone and city.\n4. BluJay reviews and approves your account. After approval you can add students and book interviews for them.", ['login']],
                ['What is BluJay Interview Operations?', "It is where BluJay candidates book interview support. You book a slot for your company interview, BluJay assigns a computer and a support person, and the evening before we confirm the office location (KPHB or Ameerpet).", ['register', 'login']],
            ],
            'candidate' => [
                ['How do I book an interview?', "Tap Book (the + button). There are 4 short steps:\n1. Details: company name, job role, interview technology, round and HR contact (or \"Not available\").\n2. Setup: Normal or Tool interview, and duration (30, 45 or 60 min).\n3. Schedule: pick a date and tap one of the available times.\n4. Review: check everything and tap Confirm interview.\nBook at least {$rules['advance']} before the start time. Booking hours: {$rules['hours']}.", ['candidate/book']],
                ['Where is my interview location?', "Open Home or My interviews. Each booked interview shows its Location: KPHB or Ameerpet. It shows \"To be confirmed\" until BluJay assigns the office, which happens the evening before your interview. Please check again that night.", ['candidate', 'candidate/interviews']],
                ['How do I reschedule or cancel?', "1. Open My interviews and tap the interview.\n2. Tap Reschedule to pick a new date and time, or Cancel interview to release the slot.\nYou can change it until {$rules['cutoff']} before the start time. After that, contact your BluJay coordinator.", ['candidate/interviews']],
                ['What is Normal vs Tool interview?', "Normal: a standard interview computer.\nTool: a computer plus a support person who helps you during the interview.\nChoose it in step 2 of booking.", ['candidate/book']],
                ['Why can\'t I see any time slots?', "Slots show only when a computer (and support person for Tool) is free.\n- Try another date or a shorter duration.\n- Slots less than {$rules['advance']} away are hidden.\n- Business hours: {$rules['hours']}.\nIf nothing is free for days, contact your BluJay coordinator.", ['candidate/book']],
                ['How do I update my skills or resume?', "Open My profile.\n- Edit Tech Stack: add or remove skills, set years for each, update total experience and tap Save Tech Stack.\n- Resume: tap Replace Resume and upload the new PDF or DOCX.\nTo change your name, phone or photo, contact your BluJay coordinator.", ['profile']],
                ['How do I see my past interviews?', "Open My interviews. Use the tabs Upcoming, Past and Cancelled.", ['candidate/interviews']],
                ['How do I sign out?', "Tap your photo or initials at the top right and choose Sign out.", []],
            ],
            'consultancy' => [
                ['How do I add a student?', "1. Open Students and tap Add student (or Home > Add a student).\n2. Upload the student's resume first: we fill in name, experience and skills.\n3. Check the details, add the student's phone number (photo is optional) and save.\nA phone number already registered with BluJay cannot be added again.", ['consultancy/students/new']],
                ['How do I book an interview for a student?', "1. Tap Book.\n2. Choose the student (search by name or phone) and tap Continue.\n3. Fill company, role, technology, round and HR details.\n4. Pick Normal or Tool, duration, date and an available time, then confirm.\nEvery booking shows the student's name, so there is no confusion.", ['consultancy/book']],
                ['Where do I see the office location?', "Each booked interview shows Location: KPHB or Ameerpet on Home and Interviews. It shows \"To be confirmed\" until BluJay assigns it the evening before. Please tell your student once it appears.", ['consultancy/interviews']],
                ['How do I reschedule or cancel?', "Open Interviews, tap the interview, then Reschedule or Cancel interview. Changes are allowed until {$rules['cutoff']} before the start time.", ['consultancy/interviews']],
                ['Where are my reports?', "Open Reports. You see calls booked, completed, upcoming and cancelled, by month, by student, by company and by technology. Use Filters to change the date range.", ['consultancy/reports']],
                ['Is there a booking limit?', "No. You can book as many interviews as you need, as long as slots are free.", ['consultancy/book']],
            ],
            'admin' => [
                ['How do I assign locations?', "Open Assign locations. Pick a date (tomorrow by default is best), then tap KPHB or Ameerpet for each interview. Candidates and consultancies see it immediately. The dashboard alert shows how many upcoming interviews still need a location.", ['admin/locations']],
                ['How do I book for a candidate?', "Tap New interview in the sidebar, choose the candidate, fill the interview details and pick a slot. Admins may book inside the candidate time cutoffs.", ['admin/interview/new']],
                ['How do I approve a consultancy?', "Open Consultancies, open the pending consultancy and tap Approve (or Reject). You can suspend or reactivate later.", ['admin/consultancies']],
                ['How do I search by skill and experience?', "Use the search box at the top. Examples: \"Gen AI 2+\", \"python 3+ yrs\", \"5+ years\", or a name, phone or company.", ['admin/candidates']],
                ['How do I change business hours or cutoffs?', "Open Settings. Set the minimum booking notice, the cancel/reschedule cutoff and weekly opening hours. Every change is recorded in the Audit log.", ['admin/settings']],
                ['Where are reports and exports?', "Open Reports for totals by date, technology, resource and support person. Use Export CSV to download.", ['admin/reports']],
            ],
        ];
        $items = $all[$audience] ?? $all['guest'];
        $pages = self::PAGES[$audience] ?? self::PAGES['guest'];
        return array_map(fn (array $faq): array => ['q' => $faq[0], 'a' => $faq[1], 'links' => $this->links($faq[2], $pages)], $items);
    }

    /** @return array{answer:string, links:array<int,array{label:string,path:string}>, source:string} */
    public function ask(string $question, string $audience, array $history = []): array
    {
        $question = $this->redact(mb_substr(trim($question), 0, 500));
        if (self::configured()) {
            try { return $this->askGemini($question, $audience, $history) + ['source' => 'ai']; }
            catch (\Throwable $error) { error_log('Help bot Gemini error: ' . $error->getMessage()); }
        }
        return $this->bestFaq($question, $audience) + ['source' => 'faq'];
    }

    private function askGemini(string $question, string $audience, array $history): array
    {
        $pages = self::PAGES[$audience] ?? self::PAGES['guest'];
        $contents = [];
        foreach (array_slice($history, -6) as $turn) {
            $text = is_array($turn) && is_string($turn['text'] ?? null) ? $this->redact(mb_substr($turn['text'], 0, 600)) : '';
            if ($text === '') continue;
            $contents[] = ['role' => ($turn['role'] ?? '') === 'bot' ? 'model' : 'user', 'parts' => [['text' => $text]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $question . $this->languageHint($question)]]];
        $body = json_encode([
            'systemInstruction' => ['parts' => [['text' => $this->instructions($audience, $pages)]]],
            'contents' => $contents,
            'generationConfig' => [
                'thinkingConfig' => ['thinkingLevel' => Config::get('HELP_BOT_THINKING', 'minimal')],
                'maxOutputTokens' => 900,
                'responseMimeType' => 'application/json',
                'responseSchema' => ['type' => 'OBJECT', 'properties' => [
                    'answer' => ['type' => 'STRING'],
                    'links' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING', 'enum' => array_keys($pages)]],
                ], 'required' => ['answer', 'links']],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $models = array_values(array_unique(array_filter(array_map(
            static fn (string $model): string => preg_replace('/[^a-z0-9.\-]/i', '', $model),
            // Chat needs speed more than depth: the lite model answers first, the main model is the fallback.
            [Config::get('HELP_BOT_MODEL', Config::get('GEMINI_FALLBACK_MODEL', 'gemini-flash-lite-latest')), Config::get('GEMINI_MODEL', 'gemini-3.6-flash')]
        ))));
        foreach ($models as $index => $model) {
            // A slow first model should not keep the visitor waiting: give it 12 s, then try the fallback.
            [$status, $response] = $this->post(sprintf(self::ENDPOINT, $model), $body, $index === 0 && count($models) > 1 ? 12 : 20);
            if ($status === 200) {
                $text = '';
                foreach (json_decode($response, true)['candidates'][0]['content']['parts'] ?? [] as $part) { if (is_string($part['text'] ?? null) && !($part['thought'] ?? false)) $text .= $part['text']; }
                $data = json_decode($text, true);
                if (is_array($data) && is_string($data['answer'] ?? null) && trim($data['answer']) !== '') {
                    $links = array_filter(is_array($data['links'] ?? null) ? $data['links'] : [], 'is_string');
                    return ['answer' => mb_substr(trim($data['answer']), 0, 2000), 'links' => $this->links(array_slice(array_values($links), 0, 3), $pages)];
                }
            }
            if ($status !== 200 && $status !== 0 && $status !== 429 && $status < 500) break;
        }
        throw new RuntimeException('Gemini request failed');
    }

    private function instructions(string $audience, array $pages): string
    {
        $who = ['guest' => 'a visitor who is not signed in (new student, existing student who cannot sign in, or a consultancy)', 'candidate' => 'a signed-in candidate (student)', 'consultancy' => 'a signed-in partner consultancy staff member', 'admin' => 'a signed-in BluJay administrator'][$audience] ?? 'a visitor';
        $knowledge = '';
        foreach (['guest', 'candidate', 'consultancy', 'admin'] as $group) {
            if ($group === 'admin' && $audience !== 'admin') continue; // admin tooling stays private
            $knowledge .= "\n## For " . $group . "\n";
            foreach ($this->faqs($group) as $faq) $knowledge .= "Q: {$faq['q']}\nA: {$faq['a']}\n";
        }
        $pageList = implode(', ', array_map(fn ($path, $label) => "\"{$path}\" ({$label})", array_keys($pages), $pages));
        $rules = $this->rules();
        return <<<TEXT
        You are "BluJay Help", the in-app assistant of BluJay Technologies Interview Operations, a web app where candidates (students) book interview support slots in Hyderabad. You are talking to {$who}.

        Answer ONLY questions about using this app: registering, signing in, Google sign-in, forgot password, booking, rescheduling, cancelling, interview locations, profile, consultancy features{$this->adminScope($audience)}. For anything else, politely say you can only help with the BluJay interview app.

        Rules:
        - Use only the facts below. Never invent features, prices, phone numbers, emails or policies. If you do not know, say so and suggest contacting their BluJay coordinator.
        - Be short and friendly. Use simple numbered steps when explaining how to do something, each step on its own new line. Name the exact button or menu text the user should tap (keep button names in English as they appear in the app).
        - Reply in the same language and script the user wrote in. If they write Telugu in English letters (e.g. "ela book cheyali"), reply in Telugu written in English letters. If they write in Telugu script, reply in Telugu script. Otherwise reply in simple English.
        - Never ask for or repeat passwords, OTP codes or other secrets. If a user shares one, tell them not to share it.
        - In "links", list up to 3 page paths that help the user go where they need, chosen only from: {$pageList}. Use an empty list if none fit.
        - Plain text only, no markdown symbols like ** or #.

        Live settings: minimum booking notice {$rules['advance']}; reschedule/cancel allowed until {$rules['cutoff']} before start; business hours {$rules['hours']}; interview durations 30, 45 or 60 minutes; offices KPHB and Ameerpet (assigned the evening before); all times are India time (IST).

        Facts:{$knowledge}
        TEXT;
    }

    /** Small models drift to English; tell them explicitly when the question is Telugu. */
    private function languageHint(string $question): string
    {
        if (preg_match('/\p{Telugu}/u', $question)) return "

(Important: reply in Telugu script, keeping app button names in English.)";
        $telugu = ['ela', 'enduku', 'cheyali', 'cheyyali', 'avvali', 'avuthundi', 'avtundi', 'ledu', 'undi', 'kavali', 'ekkada', 'emi', 'em', 'naku', 'naaku', 'nenu', 'meeru', 'cheppu', 'cheppandi', 'kanipinchatledu', 'kanipistaledu', 'raledu', 'ravatledu', 'antundi', 'chesanu', 'cheyandi', 'vachindi', 'ivvali', 'pettali', 'marchali', 'chudali', 'ante', 'enti', 'lo', 'ki', 'ni', 'ga'];
        $words = preg_split('/[^a-z]+/', strtolower($question)) ?: [];
        return count(array_intersect($words, $telugu)) >= 2 || (count($words) <= 4 && array_intersect($words, $telugu)) ? "

(Important: the question is Telugu typed in English letters. Reply the same way, in Telugu using English letters, for example: \"Sign-in page lo Create an account tap cheyandi.\" Do not reply in plain English.)" : '';
    }

    private function adminScope(string $audience): string { return $audience === 'admin' ? ', and admin operations (calendar, locations, candidates, consultancies, reports, settings, audit log)' : ''; }

    /** Simple keyword match used when Gemini is unavailable. */
    private function bestFaq(string $question, string $audience): array
    {
        $words = array_filter(preg_split('/[^a-z0-9]+/', strtolower($question)) ?: [], fn ($word) => strlen($word) > 2);
        $best = null; $bestScore = 0;
        foreach ($this->faqs($audience) as $faq) {
            $haystack = strtolower($faq['q'] . ' ' . $faq['a']);
            $score = 0;
            foreach ($words as $word) if (str_contains($haystack, $word)) $score += str_contains(strtolower($faq['q']), $word) ? 3 : 1;
            if ($score > $bestScore) { $best = $faq; $bestScore = $score; }
        }
        if ($best && $bestScore >= 3) return ['answer' => $best['a'], 'links' => $best['links']];
        return ['answer' => "Sorry, I could not find an answer to that. Try one of the questions below, or contact your BluJay coordinator for help.", 'links' => []];
    }

    private function links(array $paths, array $pages): array
    {
        $links = [];
        foreach (array_unique($paths) as $path) if (isset($pages[$path])) $links[] = ['label' => $pages[$path], 'path' => $path];
        return $links;
    }

    /** Phone numbers, OTPs and long digit runs never leave the server. */
    private function redact(string $text): string { return preg_replace('/\d[\d\s-]{5,}\d/', '[number]', $text) ?? ''; }

    /** Live scheduling rules from settings, in plain words. */
    private function rules(): array
    {
        static $rules = null;
        if ($rules !== null) return $rules;
        $minutes = static fn (int $value): string => $value % 60 === 0 && $value >= 60 ? ($value / 60) . ' hour' . ($value === 60 ? '' : 's') : $value . ' minutes';
        try {
            $db = Database::connection();
            $settings = $db->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('advance_booking_minutes','candidate_change_cutoff_minutes')")->fetchAll(\PDO::FETCH_KEY_PAIR);
            $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
            $open = [];
            foreach ($db->query('SELECT weekday, is_open, opens_at, closes_at FROM business_hours ORDER BY weekday') as $row) {
                if ((int) $row['is_open']) $open[] = ($days[(int) $row['weekday']] ?? $row['weekday']) . ' ' . date('g:i A', strtotime($row['opens_at'])) . '-' . date('g:i A', strtotime($row['closes_at']));
            }
            $rules = ['advance' => $minutes((int) ($settings['advance_booking_minutes'] ?? 60)), 'cutoff' => $minutes((int) ($settings['candidate_change_cutoff_minutes'] ?? 60)), 'hours' => $open ? implode(', ', $open) . ' (IST)' : 'set by BluJay'];
        } catch (\Throwable) {
            $rules = ['advance' => '1 hour', 'cutoff' => '1 hour', 'hours' => 'set by BluJay'];
        }
        return $rules;
    }

    /** @return array{int,string} */
    private function post(string $url, string $body, int $timeout = 20): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . Config::get('GEMINI_API_KEY')],
            CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => $timeout,
        ]);
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($status !== 200) error_log('Help bot Gemini failed: HTTP ' . $status . ' ' . ($error ?: substr((string) $response, 0, 300)));
        return [$status, is_string($response) ? $response : ''];
    }
}
