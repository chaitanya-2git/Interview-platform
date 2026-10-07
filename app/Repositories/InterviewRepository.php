<?php
declare(strict_types=1);
namespace App\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class InterviewRepository
{
    private const SELECT = "SELECT i.*, COALESCE(u.full_name,i.candidate_name_snapshot) AS candidate_name, u.phone AS candidate_phone,
        (u.id IS NULL) AS candidate_deleted,p.photo_filename,p.updated_at AS candidate_photo_updated_at,
        t.name AS technology_name, r.name AS resource_name, sp.name AS support_person_name
        FROM interviews i
        LEFT JOIN users u ON u.id = i.candidate_id
        LEFT JOIN candidate_profiles p ON p.user_id = i.candidate_id
        JOIN technologies t ON t.id = i.technology_id
        JOIN resources r ON r.id = i.resource_id
        LEFT JOIN support_people sp ON sp.id = i.support_person_id";

    public function __construct(private readonly PDO $db) {}

    public function candidateUpcoming(int $candidateId, string $nowUtc, ?int $limit = null): array
    {
        $sql = self::SELECT . " WHERE i.candidate_id = ? AND i.status = 'BOOKED' AND i.end_time >= ? ORDER BY i.start_time" . ($limit ? ' LIMIT ' . $limit : '');
        $query = $this->db->prepare($sql); $query->execute([$candidateId, $nowUtc]); return $query->fetchAll();
    }

    public function candidatePast(int $candidateId, string $nowUtc, ?int $limit = null): array
    {
        $sql = self::SELECT . " WHERE i.candidate_id = ? AND i.status = 'BOOKED' AND i.end_time < ? ORDER BY i.start_time DESC" . ($limit ? ' LIMIT ' . $limit : '');
        $query = $this->db->prepare($sql); $query->execute([$candidateId, $nowUtc]); return $query->fetchAll();
    }

    public function candidateCancelled(int $candidateId): array
    {
        $query = $this->db->prepare(self::SELECT . " WHERE i.candidate_id = ? AND i.status = 'CANCELLED' ORDER BY i.start_time DESC");
        $query->execute([$candidateId]); return $query->fetchAll();
    }

    public function candidateDetail(int $candidateId, int $interviewId): ?array
    {
        $query = $this->db->prepare(self::SELECT . ' WHERE i.id = ? AND i.candidate_id = ?');
        $query->execute([$interviewId, $candidateId]); return $query->fetch() ?: null;
    }

    public function adminDetail(int $interviewId): ?array
    {
        $query = $this->db->prepare(self::SELECT . ' WHERE i.id = ?');
        $query->execute([$interviewId]); return $query->fetch() ?: null;
    }

    public function adminSearch(array $filters, DateTimeZone $timezone): array
    {
        return $this->adminPaginated($filters, $timezone, 1, 250)['rows'];
    }

    public function adminPaginated(array $filters, DateTimeZone $timezone, int $page = 1, int $perPage = 25): array
    {
        $where = ['1 = 1']; $parameters = [];
        $search = trim(is_string($filters['search'] ?? null) ? $filters['search'] : '');
        if ($search !== '') {
            $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 100)) . '%';
            $where[] = "(COALESCE(u.full_name,i.candidate_name_snapshot) LIKE ? ESCAPE '\\\\' OR u.phone LIKE ? ESCAPE '\\\\' OR i.company_name LIKE ? ESCAPE '\\\\' OR i.job_role LIKE ? ESCAPE '\\\\')";
            array_push($parameters, $term, $term, $term, $term);
        }
        $type = strtoupper(is_string($filters['type'] ?? null) ? $filters['type'] : '');
        if (in_array($type, ['TOOL', 'NORMAL'], true)) { $where[] = 'i.interview_type = ?'; $parameters[] = $type; }
        foreach (['technology_id' => 'i.technology_id', 'support_person_id' => 'i.support_person_id', 'resource_id' => 'i.resource_id'] as $key => $column) {
            $id = filter_var($filters[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id) { $where[] = $column . ' = ?'; $parameters[] = $id; }
        }
        $utc = new DateTimeZone('UTC');
        foreach (['date_from' => '>=', 'date_to' => '<'] as $key => $operator) {
            $date = is_string($filters[$key] ?? null) ? $filters[$key] : '';
            $local = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
            if ($local && $local->format('Y-m-d') === $date) {
                $where[] = 'i.start_time ' . $operator . ' ?';
                $parameters[] = ($key === 'date_to' ? $local->modify('+1 day') : $local)->setTimezone($utc)->format('Y-m-d H:i:s');
            }
        }
        $whereSql=implode(' AND ',$where);$count=$this->db->prepare('SELECT COUNT(*) FROM interviews i LEFT JOIN users u ON u.id=i.candidate_id WHERE '.$whereSql);$count->execute($parameters);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=min(max(1,$page),$pages);
        $offset=($page-1)*$perPage;$query = $this->db->prepare(self::SELECT . ' WHERE ' . $whereSql . " ORDER BY i.start_time DESC LIMIT $perPage OFFSET $offset");
        $query->execute($parameters); return ['rows'=>$query->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
}
