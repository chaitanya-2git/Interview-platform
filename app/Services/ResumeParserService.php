<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\TechnologyRepository;
use App\Support\{Config, Database};
use App\Validation\ValidationException;
use RuntimeException;

/**
 * Reads a resume with Google Gemini and returns profile suggestions (name, total experience,
 * skills with years) mapped onto the BluJay technology catalog. Suggestions only: the candidate
 * reviews and can change every value before the form is submitted.
 */
final class ResumeParserService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const MAX_SKILLS = CandidateTechStackService::MAX_SKILLS;
    private const MAX_TEXT_CHARS = 60000;

    public static function configured(): bool { return Config::get('GEMINI_API_KEY') !== ''; }

    /** @return array{full_name:string,experience_years:?float,skills:list<array{id:int,name:string,years:?float}>,unmatched:list<string>} */
    public function parse(array $file): array
    {
        if (!self::configured()) { throw new RuntimeException('Gemini is not configured'); }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException(['resume' => 'Choose a PDF or DOCX resume up to 5 MB.']);
        }
        $db = Database::connection();
        $meta = (new ResumeService($db, new AuditService($db)))->validate($file['tmp_name'], is_string($file['name'] ?? null) ? $file['name'] : 'resume');
        $technologies = (new TechnologyRepository($db))->picker(true);

        $document = $meta['mime_type'] === 'application/pdf'
            ? ['inline_data' => ['mime_type' => 'application/pdf', 'data' => base64_encode((string) file_get_contents($file['tmp_name']))]]
            : ['text' => "Resume text:\n" . $this->docxText($file['tmp_name'])];

        $result = $this->generate([$document, ['text' => $this->instructions($technologies)]]);
        return $this->normalize($result, $technologies);
    }

    private function instructions(array $technologies): string
    {
        $catalog = implode("\n", array_map(static fn($t) => $t['name'] . ($t['aliases'] !== '' ? ' (also: ' . $t['aliases'] . ')' : ''), $technologies));
        return <<<TEXT
        Extract the candidate's profile from the resume above for an interview-support platform.

        - full_name: the candidate's name as written on the resume ("" if absent).
        - total_experience_years: total professional work experience in years, rounded to the nearest 0.5. Count internships only when they are the only experience. Use 0 for a fresher and null only if it cannot be estimated at all.
        - skills: technologies and skills the candidate has actually used, most significant first, at most 20. For each, "name" must be copied exactly from the catalog below when the skill matches a catalog entry or one of its aliases (for example "GenAI" or "LLM apps" -> "Generative AI"). Include skills that are not in the catalog with their usual name. "years": years of hands-on experience with that skill, estimated from the dates of the roles and projects that mention it, rounded to the nearest 0.5; null if the resume gives no basis for an estimate.

        Base every value on the resume only. Do not invent skills or experience.

        Catalog:
        {$catalog}
        TEXT;
    }

    private function generate(array $parts): array
    {
        $models = array_values(array_unique(array_filter(array_map(
            static fn(string $model): string => preg_replace('/[^a-z0-9.\-]/i', '', $model),
            [Config::get('GEMINI_MODEL', 'gemini-3.6-flash'), Config::get('GEMINI_FALLBACK_MODEL', 'gemini-flash-lite-latest')]
        ))));
        $body = json_encode([
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => [
                'thinkingConfig' => ['thinkingLevel' => 'low'],
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'full_name' => ['type' => 'STRING'],
                        'total_experience_years' => ['type' => 'NUMBER', 'nullable' => true],
                        'skills' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => [
                            'name' => ['type' => 'STRING'],
                            'years' => ['type' => 'NUMBER', 'nullable' => true],
                        ], 'required' => ['name', 'years']]],
                    ],
                    'required' => ['full_name', 'total_experience_years', 'skills'],
                ],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        foreach ($models as $model) {
            [$status, $response] = $this->post(sprintf(self::ENDPOINT, $model), $body);
            if ($status === 200) {
                $payload = json_decode($response, true);
                $text = '';
                foreach ($payload['candidates'][0]['content']['parts'] ?? [] as $part) { if (is_string($part['text'] ?? null) && !($part['thought'] ?? false)) { $text .= $part['text']; } }
                $data = json_decode($text, true);
                if (is_array($data)) { return $data; }
                error_log("Gemini resume parse ({$model}): unexpected response " . substr($response, 0, 300));
            }
            // Busy, rate-limited or timed out: try the fallback model. Other errors (bad key, bad request) stop here.
            if ($status !== 200 && $status !== 0 && $status !== 429 && $status < 500) { break; }
        }
        throw new RuntimeException('Gemini request failed');
    }

    /** @return array{int,string} HTTP status (0 = network error or timeout) and body */
    private function post(string $url, string $body): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . Config::get('GEMINI_API_KEY')],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 35,
        ]);
        // Windows PHP builds ship without a CA bundle; use the OS certificate store there.
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) { curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA); }
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($status !== 200) { error_log('Gemini resume parse failed: HTTP ' . $status . ' ' . ($error ?: substr((string) $response, 0, 300))); }
        return [$status, is_string($response) ? $response : ''];
    }

    private function normalize(array $data, array $technologies): array
    {
        $key = static fn(string $value): string => preg_replace('/[^a-z0-9+#]/', '', mb_strtolower($value));
        $lookup = [];
        foreach ($technologies as $technology) {
            $lookup[$key($technology['name'])] ??= $technology;
            foreach (array_filter(array_map('trim', explode('|', (string) $technology['aliases']))) as $alias) { $lookup[$key($alias)] ??= $technology; }
        }
        $years = static function (mixed $value): ?float {
            if (!is_int($value) && !is_float($value)) { return null; }
            return max(0.0, min(50.0, round((float) $value * 2) / 2));
        };
        $skills = [];
        $unmatched = [];
        foreach (is_array($data['skills'] ?? null) ? $data['skills'] : [] as $skill) {
            $name = is_string($skill['name'] ?? null) ? trim($skill['name']) : '';
            if ($name === '') { continue; }
            $technology = $lookup[$key($name)] ?? null;
            if ($technology === null) { $unmatched[mb_strtolower($name)] = mb_substr($name, 0, 60); continue; }
            $id = (int) $technology['id'];
            $value = $years($skill['years'] ?? null);
            if (!isset($skills[$id]) || ($value ?? -1) > ($skills[$id]['years'] ?? -1)) {
                $skills[$id] = ['id' => $id, 'name' => $technology['name'], 'years' => $value];
            }
        }
        $name = is_string($data['full_name'] ?? null) ? trim(preg_replace('/[\x00-\x1f]/', '', $data['full_name'])) : '';
        return [
            'full_name' => mb_substr($name, 0, 120),
            'experience_years' => $years($data['total_experience_years'] ?? null),
            'skills' => array_slice(array_values($skills), 0, self::MAX_SKILLS),
            'unmatched' => array_slice(array_values($unmatched), 0, 15),
        ];
    }

    private function docxText(string $path): string
    {
        if (!class_exists(\ZipArchive::class)) { throw new RuntimeException('The zip extension is required to read DOCX resumes'); }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) { throw new ValidationException(['resume' => 'This DOCX resume could not be read.']); }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        $xml = preg_replace(['/<w:tab\/>/', '/<\/w:p>/', '/<w:br[^>]*\/>/'], ["\t", "\n", "\n"], $xml);
        $text = trim(html_entity_decode(strip_tags((string) $xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        if ($text === '') { throw new ValidationException(['resume' => 'This DOCX resume has no readable text.']); }
        return mb_substr($text, 0, self::MAX_TEXT_CHARS);
    }
}
