<?php
declare(strict_types=1);
namespace App\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class AdminRepository
{
    public function __construct(private readonly PDO $db) {}

    public function activeResources(): array
    {
        return $this->db->query("SELECT id,name,resource_type FROM resources WHERE active=1 ORDER BY FIELD(resource_type,'TOOL','NORMAL'),name")->fetchAll();
    }

    public function calendar(DateTimeImmutable $fromLocal, DateTimeImmutable $toLocal): array
    {
        $utc = new DateTimeZone('UTC');
        $query = $this->db->prepare("SELECT i.*,COALESCE(u.full_name,i.candidate_name_snapshot) candidate_name,u.phone candidate_phone,(u.id IS NULL) candidate_deleted,p.photo_filename,p.updated_at candidate_photo_updated_at,t.name technology_name,r.name resource_name,sp.name support_person_name
            FROM interviews i LEFT JOIN users u ON u.id=i.candidate_id LEFT JOIN candidate_profiles p ON p.user_id=i.candidate_id JOIN technologies t ON t.id=i.technology_id JOIN resources r ON r.id=i.resource_id LEFT JOIN support_people sp ON sp.id=i.support_person_id
            WHERE i.status='BOOKED' AND i.start_time>=? AND i.start_time<? ORDER BY i.start_time,r.name");
        $query->execute([$fromLocal->setTimezone($utc)->format('Y-m-d H:i:s'), $toLocal->setTimezone($utc)->format('Y-m-d H:i:s')]);
        return $query->fetchAll();
    }

    public function dashboard(DateTimeImmutable $nowLocal): array
    {
        $rows = $this->calendar($nowLocal->setTime(0,0), $nowLocal->modify('+1 day')->setTime(0,0));
        $now = $nowLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $metrics = ['today'=>count($rows),'tool'=>0,'normal'=>0,'remaining'=>0]; $current=null; $next=null;
        foreach ($rows as $row) {
            $metrics[strtolower($row['interview_type'])]++;
            if ($row['end_time'] > $now) { $metrics['remaining']++; }
            if ($row['start_time'] <= $now && $row['end_time'] > $now) { $current = $row; }
            if ($next === null && $row['start_time'] > $now) { $next = $row; }
        }
        $active = $this->activeResources();
        $busy = [];
        foreach ($rows as $row) { if ($row['start_time'] <= $now && $row['end_time'] > $now) { $busy[(int)$row['resource_id']] = true; } }
        $metrics['available'] = count($active) - count($busy);
        return compact('rows','metrics','current','next','active');
    }

    public function resources(string $nowUtc): array
    {
        $query=$this->db->prepare("SELECT r.*,COUNT(i.id) upcoming_count,MIN(i.start_time) next_start FROM resources r LEFT JOIN interviews i ON i.resource_id=r.id AND i.status='BOOKED' AND i.start_time>? GROUP BY r.id ORDER BY FIELD(r.resource_type,'TOOL','NORMAL'),r.name");
        $query->execute([$nowUtc]); return $query->fetchAll();
    }

    public function supportPeople(string $dayStartUtc, string $dayEndUtc, string $nowUtc): array
    {
        $query=$this->db->prepare("SELECT sp.*,SUM(CASE WHEN i.start_time>=? AND i.start_time<? THEN 1 ELSE 0 END) today_count,SUM(CASE WHEN i.start_time>? THEN 1 ELSE 0 END) upcoming_count,MIN(CASE WHEN i.start_time>? THEN i.start_time END) next_start FROM support_people sp LEFT JOIN interviews i ON i.support_person_id=sp.id AND i.status='BOOKED' GROUP BY sp.id ORDER BY sp.name");
        $query->execute([$dayStartUtc,$dayEndUtc,$nowUtc,$nowUtc]); return $query->fetchAll();
    }

    public function candidates(array $filters, int $page=1, int $perPage=20): array
    {
        $where=["u.role='CANDIDATE'"]; $params=[];
        $search=trim((string)($filters['search']??''));
        if($search!==''){ $term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],mb_substr($search,0,100)).'%'; $where[]="(u.full_name LIKE ? ESCAPE '\\\\' OR u.phone LIKE ? ESCAPE '\\\\')"; array_push($params,$term,$term); }
        $id=filter_var($filters['technology_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id){$where[]='(p.technology_id=? OR EXISTS(SELECT 1 FROM candidate_technologies ct WHERE ct.candidate_id=u.id AND ct.technology_id=?))';array_push($params,$id,$id);}
        $classification=(string)($filters['classification']??''); if(in_array($classification,['Genuine','Fake'],true)){$where[]='p.classification=?';$params[]=$classification;}
        $status=(string)($filters['status']??'');if(in_array($status,['active','inactive'],true)){$where[]='u.active=?';$params[]=$status==='active'?1:0;}
        $base=" FROM users u JOIN candidate_profiles p ON p.user_id=u.id LEFT JOIN candidate_resumes cr ON cr.candidate_id=u.id WHERE ".implode(' AND ',$where);
        $count=$this->db->prepare('SELECT COUNT(*)'.$base);$count->execute($params);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=min(max(1,$page),$pages);
        $offset=($page-1)*$perPage;
        $sql="SELECT u.id,u.full_name,u.phone,u.active,p.classification,p.photo_filename,p.updated_at profile_updated_at,cr.original_filename resume_original_filename,cr.mime_type resume_mime_type,cr.file_size resume_file_size,cr.uploaded_at resume_uploaded_at,
            (SELECT COUNT(*) FROM interviews i WHERE i.candidate_id=u.id AND i.status='BOOKED' AND i.end_time>UTC_TIMESTAMP()) upcoming_count,
            (SELECT MIN(i.start_time) FROM interviews i WHERE i.candidate_id=u.id AND i.status='BOOKED' AND i.end_time>UTC_TIMESTAMP()) next_start".$base." ORDER BY u.full_name LIMIT $perPage OFFSET $offset";
        $query=$this->db->prepare($sql);$query->execute($params);$rows=(new CandidateTechnologyRepository($this->db))->attach($query->fetchAll());return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages];
    }

    public function candidate(int $id): ?array
    {
        $q=$this->db->prepare("SELECT u.id,u.full_name,u.phone,u.active,p.classification,p.photo_filename,p.updated_at profile_updated_at,cr.original_filename resume_original_filename,cr.mime_type resume_mime_type,cr.file_size resume_file_size,cr.uploaded_at resume_uploaded_at FROM users u JOIN candidate_profiles p ON p.user_id=u.id LEFT JOIN candidate_resumes cr ON cr.candidate_id=u.id WHERE u.id=? AND u.role='CANDIDATE'");$q->execute([$id]);$row=$q->fetch();return $row?(new CandidateTechnologyRepository($this->db))->attach([$row])[0]:null;
    }

    public function candidateInterviewCount(int $id): int
    {
        $query=$this->db->prepare('SELECT COUNT(*) FROM interviews WHERE candidate_id=?');$query->execute([$id]);return (int)$query->fetchColumn();
    }
}
