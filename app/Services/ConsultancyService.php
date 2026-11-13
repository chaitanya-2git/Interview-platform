<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\CandidateTechnologyRepository;
use App\Support\Database;
use App\Validation\{Input, ValidationException};
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Partner consultancies: they sign in with Google, wait for BluJay approval, then add their
 * students (candidate records they manage) and book interview slots for them.
 */
final class ConsultancyService
{
    public const STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'SUSPENDED'];

    private function db(): PDO { return Database::connection(); }

    /** Creates the consultancy login (from a verified Google identity) and its pending application. */
    public function register(array $google, array $data): int
    {
        if (!(new RateLimiter())->allow('consultancy-register-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 5, 3600)) {
            throw new ValidationException(['form' => 'Too many registration attempts. Please try again in one hour.']);
        }
        $details = $this->validate($data);
        $db = $this->db();
        $db->beginTransaction();
        try {
            $query = $db->prepare("INSERT INTO users (role, full_name, email, google_sub, password_hash) VALUES ('CONSULTANCY', ?, ?, ?, ?)");
            $query->execute([$details['contact_name'], $google['email'], $google['sub'], password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
            $userId = (int) $db->lastInsertId();
            $query = $db->prepare('INSERT INTO consultancies (owner_user_id, name, contact_name, phone, city, website) VALUES (?, ?, ?, ?, ?, ?)');
            $query->execute([$userId, $details['name'], $details['contact_name'], $details['phone'], $details['city'], $details['website']]);
            $id = (int) $db->lastInsertId();
            (new AuditService($db))->record($userId, 'consultancy.registered', 'consultancy', $id, ['name' => $details['name']]);
            $db->commit();
            return $userId;
        } catch (\PDOException $error) {
            if ($db->inTransaction()) { $db->rollBack(); }
            if (($error->errorInfo[1] ?? null) === 1062) {
                $detail = (string) ($error->errorInfo[2] ?? '');
                throw new ValidationException(['form' => str_contains($detail, 'email')
                    ? 'This Google account email is already used by another BluJay account.'
                    : 'This Google account is already connected to a BluJay account. Please sign in.']);
            }
            throw $error;
        } catch (\Throwable $error) {
            if ($db->inTransaction()) { $db->rollBack(); }
            throw $error;
        }
    }

    public function forUser(int $userId): ?array
    {
        $query = $this->db()->prepare('SELECT * FROM consultancies WHERE owner_user_id = ?');
        $query->execute([$userId]);
        return $query->fetch() ?: null;
    }

    public function find(int $id): ?array
    {
        $query = $this->db()->prepare('SELECT c.*, u.email owner_email, u.active owner_active, r.full_name reviewer_name FROM consultancies c JOIN users u ON u.id = c.owner_user_id LEFT JOIN users r ON r.id = c.reviewed_by WHERE c.id = ?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    /** Admin list with student and interview counts. */
    public function directory(string $status = ''): array
    {
        $where = in_array($status, self::STATUSES, true) ? 'WHERE c.status = ?' : '';
        $query = $this->db()->prepare("SELECT c.*, u.email owner_email,
            (SELECT COUNT(*) FROM candidate_profiles p WHERE p.consultancy_id = c.id) students,
            (SELECT COUNT(*) FROM interviews i WHERE i.consultancy_id = c.id AND i.status = 'BOOKED') booked,
            (SELECT COUNT(*) FROM interviews i WHERE i.consultancy_id = c.id AND i.status = 'BOOKED' AND i.end_time >= UTC_TIMESTAMP()) upcoming
            FROM consultancies c JOIN users u ON u.id = c.owner_user_id {$where}
            ORDER BY FIELD(c.status, 'PENDING', 'APPROVED', 'SUSPENDED', 'REJECTED'), c.created_at DESC");
        $query->execute($where ? [$status] : []);
        return $query->fetchAll();
    }

    public function counts(): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db()->query('SELECT status, COUNT(*) total FROM consultancies GROUP BY status') as $row) { $counts[$row['status']] = (int) $row['total']; }
        return $counts;
    }

    public function review(int $adminId, int $id, string $action, string $note = ''): string
    {
        $next = ['approve' => 'APPROVED', 'reject' => 'REJECTED', 'suspend' => 'SUSPENDED', 'reactivate' => 'APPROVED'][$action] ?? null;
        if ($next === null) { throw new ValidationException(['form' => 'Choose a valid action.']); }
        $note = mb_substr(trim(preg_replace('/[\x00-\x1f]/', ' ', $note)), 0, 255);
        $consultancy = $this->find($id);
        if (!$consultancy) { throw new ValidationException(['form' => 'Consultancy not found.']); }
        $query = $this->db()->prepare('UPDATE consultancies SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ?');
        $query->execute([$next, $note !== '' ? $note : null, $adminId, $id]);
        (new AuditService())->record($adminId, 'admin.consultancy.' . $action, 'consultancy', $id, ['from' => $consultancy['status'], 'to' => $next, 'note' => $note]);
        return $next;
    }

    public function addStudent(array $consultancy, int $actorId, array $data, ?array $photo, ?array $resume): int
    {
        $this->requireApproved($consultancy);
        $id = (new AuthService())->createManagedCandidate($data, $photo, $resume, (int) $consultancy['id']);
        (new AuditService())->record($actorId, 'consultancy.student.added', 'user', $id, ['consultancy_id' => (int) $consultancy['id']]);
        return $id;
    }

    public function students(int $consultancyId, string $search = ''): array
    {
        $params = [$consultancyId];
        $filter = '';
        if ($search !== '') {
            $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 80)) . '%';
            $filter = " AND (u.full_name LIKE ? ESCAPE '\\\\' OR u.phone LIKE ? ESCAPE '\\\\')";
            array_push($params, $term, $term);
        }
        $query = $this->db()->prepare("SELECT u.id, u.full_name, u.phone, u.active, p.experience_years, p.classification, p.created_at,
            (SELECT COUNT(*) FROM interviews i WHERE i.candidate_id = u.id AND i.status = 'BOOKED') booked,
            (SELECT COUNT(*) FROM interviews i WHERE i.candidate_id = u.id AND i.status = 'BOOKED' AND i.end_time >= UTC_TIMESTAMP()) upcoming,
            (SELECT MIN(i.start_time) FROM interviews i WHERE i.candidate_id = u.id AND i.status = 'BOOKED' AND i.end_time >= UTC_TIMESTAMP()) next_start
            FROM users u JOIN candidate_profiles p ON p.user_id = u.id
            WHERE p.consultancy_id = ? AND u.role = 'CANDIDATE'{$filter} ORDER BY u.full_name");
        $query->execute($params);
        return (new CandidateTechnologyRepository($this->db()))->attach($query->fetchAll());
    }

    /** One student of this consultancy, or null (ownership check for every student action). */
    public function student(int $consultancyId, int $studentId): ?array
    {
        $query = $this->db()->prepare("SELECT u.id, u.full_name, u.phone, u.active, p.experience_years, p.classification FROM users u JOIN candidate_profiles p ON p.user_id = u.id WHERE u.id = ? AND p.consultancy_id = ? AND u.role = 'CANDIDATE'");
        $query->execute([$studentId, $consultancyId]);
        $row = $query->fetch();
        return $row ? (new CandidateTechnologyRepository($this->db()))->attach([$row])[0] : null;
    }

    /** Student who owns an interview booked under this consultancy. */
    public function interviewStudent(int $consultancyId, int $interviewId): ?int
    {
        $query = $this->db()->prepare('SELECT i.candidate_id FROM interviews i JOIN candidate_profiles p ON p.user_id = i.candidate_id WHERE i.id = ? AND p.consultancy_id = ?');
        $query->execute([$interviewId, $consultancyId]);
        $id = $query->fetchColumn();
        return $id ? (int) $id : null;
    }

    /** Interview counts for the consultancy dashboard and reports page. */
    public function report(int $consultancyId, DateTimeZone $timezone, ?string $from = null, ?string $to = null): array
    {
        $utc = new DateTimeZone('UTC');
        $range = '';
        $params = [$consultancyId];
        foreach (['from' => '>=', 'to' => '<'] as $key => $operator) {
            $value = $key === 'from' ? $from : $to;
            $local = $value ? DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone) : false;
            if ($local && $local->format('Y-m-d') === $value) {
                $range .= " AND i.start_time {$operator} ?";
                $params[] = ($key === 'to' ? $local->modify('+1 day') : $local)->setTimezone($utc)->format('Y-m-d H:i:s');
            }
        }
        $base = "FROM interviews i WHERE i.consultancy_id = ?{$range}";
        $q = $this->db()->prepare("SELECT
            SUM(i.status = 'BOOKED') booked,
            SUM(i.status = 'BOOKED' AND i.end_time < UTC_TIMESTAMP()) completed,
            SUM(i.status = 'BOOKED' AND i.end_time >= UTC_TIMESTAMP()) upcoming,
            SUM(i.status = 'CANCELLED') cancelled,
            SUM(i.status = 'BOOKED' AND i.interview_type = 'TOOL') tool,
            SUM(i.status = 'BOOKED' AND i.interview_type = 'NORMAL') normal,
            COALESCE(SUM(CASE WHEN i.status = 'BOOKED' THEN i.duration_minutes END), 0) minutes,
            COUNT(DISTINCT CASE WHEN i.status = 'BOOKED' THEN i.candidate_id END) students
            {$base}");
        $q->execute($params);
        $totals = array_map('intval', $q->fetch() ?: []);
        $group = function (string $select, string $groupBy, string $order) use ($base, $params): array {
            $q = $this->db()->prepare("SELECT {$select}, SUM(i.status = 'BOOKED') booked, SUM(i.status = 'BOOKED' AND i.end_time < UTC_TIMESTAMP()) completed, SUM(i.status = 'CANCELLED') cancelled {$base} GROUP BY {$groupBy} ORDER BY {$order} LIMIT 50");
            $q->execute($params);
            return $q->fetchAll();
        };
        $offset = (new DateTimeImmutable('now', $timezone))->format('P');
        return [
            'totals' => $totals,
            'by_month' => $group("DATE_FORMAT(CONVERT_TZ(i.start_time, '+00:00', '{$offset}'), '%Y-%m') label", 'label', 'label DESC'),
            'by_student' => $group('COALESCE((SELECT full_name FROM users WHERE id = i.candidate_id), i.candidate_name_snapshot) label', 'i.candidate_reference_id, label', 'booked DESC, label'),
            'by_company' => $group('i.company_name label', 'i.company_name', 'booked DESC, label'),
            'by_technology' => $group('(SELECT name FROM technologies WHERE id = i.technology_id) label', 'i.technology_id', 'booked DESC, label'),
        ];
    }

    public function requireApproved(array $consultancy): void
    {
        if ($consultancy['status'] !== 'APPROVED') { throw new ValidationException(['form' => 'Your consultancy account is not approved for bookings yet.']); }
    }

    private function validate(array $data): array
    {
        $name = Input::text($data, 'name');
        $contact = Input::text($data, 'contact_name');
        $phone = Input::phone(Input::text($data, 'phone'));
        $city = Input::text($data, 'city');
        $website = Input::text($data, 'website');
        $errors = [];
        $clean = static fn(string $v, int $min, int $max): bool => mb_strlen($v) >= $min && mb_strlen($v) <= $max && !preg_match('/[\x00-\x1f]/', $v);
        if (!$clean($name, 2, 160)) { $errors['name'] = 'Enter the consultancy name (2–160 characters).'; }
        if (!$clean($contact, 2, 120)) { $errors['contact_name'] = 'Enter the contact person name.'; }
        if ($phone === null) { $errors['phone'] = 'Enter a 10-digit Indian mobile number or an international number with +country code.'; }
        if ($city !== '' && !$clean($city, 2, 80)) { $errors['city'] = 'Enter a valid city.'; }
        if ($website !== '') {
            if (!preg_match('#^https?://#i', $website)) { $website = 'https://' . $website; }
            if (mb_strlen($website) > 190 || !filter_var($website, FILTER_VALIDATE_URL)) { $errors['website'] = 'Enter a valid website address.'; }
        }
        if ($errors) { throw new ValidationException($errors); }
        return ['name' => $name, 'contact_name' => $contact, 'phone' => $phone, 'city' => $city !== '' ? $city : null, 'website' => $website !== '' ? $website : null];
    }
}
