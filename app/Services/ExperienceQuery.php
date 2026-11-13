<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\TechnologyRepository;
use PDO;

/**
 * Understands experience searches such as "Gen AI 2+", "python 3 years", "3+ yrs react",
 * "java >= 4" or "5+ years" (total experience). Returns null for ordinary text searches.
 */
final class ExperienceQuery
{
    /** @return array{min_years:float,technology_ids:list<int>,skill:string,label:string}|null */
    public static function parse(PDO $db, string $query): ?array
    {
        $q = mb_strtolower(trim(preg_replace('/\s+/', ' ', $query)));
        $words = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'fifteen' => 15, 'twenty' => 20];
        $q = preg_replace_callback('/\b(' . implode('|', array_keys($words)) . ')\b/u', static fn($m) => (string) $words[$m[1]], $q);
        $number = '(?<n>\d{1,2}(?:\.\d)?)';
        $unit = '(?:\s*(?:\+|plus))?(?:\s*(?:years?|yrs?|y))?(?:\s*(?:\+|plus))?(?:\s*(?:of\s+)?(?:exp(?:erience)?))?';
        $skill = null;
        if (preg_match("/^(?<skill>.*?[a-z#+].*?)\\s*(?:>=|>|at least|min(?:imum)?)?\\s*{$number}{$unit}$/u", $q, $m)) { $skill = $m['skill']; }
        elseif (preg_match("/^(?:>=|>|at least|min(?:imum)?)?\\s*{$number}{$unit}\\s*(?:in\\s+|with\\s+)?(?<skill>.*)$/u", $q, $m)) { $skill = $m['skill']; }
        else { return null; }
        $years = (float) $m['n'];
        if ($years > 50) { return null; }
        $skill = trim(preg_replace('/\b(?:in|with|of|experience|exp|years?|yrs?)\b/u', ' ', $skill));
        $skill = trim(preg_replace('/\s+/', ' ', $skill));
        $label = rtrim(rtrim(number_format($years, 1, '.', ''), '0'), '.') . '+ yrs';

        if ($skill === '' && !preg_match('/\+|plus|\by(?:ea)?rs?\b|\bexp/u', $q)) { return null; } // bare numbers stay a phone/ID search
        if ($skill === '') { return ['min_years' => $years, 'technology_ids' => [], 'skill' => '', 'label' => 'Total experience ' . $label]; }

        $ids = self::technologies($db, $skill);
        if (!$ids) { return null; }
        if (count($ids) === 1) {
            $name = $db->prepare('SELECT name FROM technologies WHERE id = ?');
            $name->execute([$ids[0]]);
            $label = ($name->fetchColumn() ?: $skill) . ' ' . $label;
        } else {
            $label = $skill . ' ' . $label;
        }
        return ['min_years' => $years, 'technology_ids' => $ids, 'skill' => $skill, 'label' => $label];
    }

    /** Technology IDs whose name or alias matches the typed skill ("gen ai" -> Generative AI via alias "GenAI"). @return list<int> */
    private static function technologies(PDO $db, string $skill): array
    {
        $key = static fn(string $value): string => preg_replace('/[^a-z0-9+#]/', '', mb_strtolower($value));
        $term = $key($skill);
        if (strlen($term) < 2) { return []; }
        $exact = []; $prefix = []; $contains = [];
        foreach ((new TechnologyRepository($db))->picker(false) as $technology) {
            $names = array_merge([$technology['name']], array_filter(array_map('trim', explode('|', (string) $technology['aliases']))));
            foreach ($names as $name) {
                $value = $key($name);
                $id = (int) $technology['id'];
                if ($value === $term) { $exact[$id] = $id; }
                elseif (str_starts_with($value, $term)) { $prefix[$id] = $id; }
                elseif (strlen($term) >= 3 && str_contains($value, $term)) { $contains[$id] = $id; }
            }
        }
        return array_values($exact ?: ($prefix ?: $contains));
    }
}
