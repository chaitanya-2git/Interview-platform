<?php
declare(strict_types=1);
namespace App\Repositories;

use PDO;

final class ReportRepository
{
    public function __construct(private readonly PDO $db) {}

    public function metrics(array $filter): array
    {
        [$where,$params]=$this->scope($filter);
        $q=$this->db->prepare("SELECT COUNT(*) total_interviews,
            COALESCE(SUM(i.interview_type='TOOL'),0) tool_interviews,
            COALESCE(SUM(i.interview_type='NORMAL'),0) normal_interviews,
            COALESCE(SUM(i.status='CANCELLED'),0) cancelled_interviews,
            COUNT(DISTINCT i.candidate_reference_id) unique_candidates,
            COALESCE(SUM(CASE WHEN i.status='BOOKED' THEN i.duration_minutes ELSE 0 END),0) scheduled_minutes
            ".$this->from()." WHERE $where");
        $q->execute($params); return $q->fetch();
    }

    public function trend(array $filter, bool $monthly=false): array
    {
        [$where,$params]=$this->scope($filter);$format=$monthly?'%Y-%m':'%Y-%m-%d';
        $sql="SELECT DATE_FORMAT(CONVERT_TZ(i.start_time,'+00:00',?), '$format') bucket, COUNT(*) total,
            SUM(i.status='BOOKED') booked, SUM(i.status='CANCELLED') cancelled
            ".$this->from()." WHERE $where GROUP BY bucket ORDER BY bucket";
        $q=$this->db->prepare($sql);$q->execute(array_merge([$filter['utc_offset']],$params));return $q->fetchAll();
    }

    public function technologies(array $filter): array
    {
        [$where,$params]=$this->scope($filter);$q=$this->db->prepare("SELECT t.id,t.name,COUNT(*) total,
            SUM(i.interview_type='TOOL') tool_count,SUM(i.interview_type='NORMAL') normal_count,
            SUM(i.status='CANCELLED') cancelled_count ".$this->from()." WHERE $where GROUP BY t.id,t.name ORDER BY total DESC,t.name");
        $q->execute($params);return $q->fetchAll();
    }

    public function classifications(array $filter): array
    {
        [$where,$params]=$this->scope($filter);$q=$this->db->prepare("SELECT COALESCE(cp.classification,i.candidate_classification_snapshot) classification,COUNT(*) interview_count,
            COUNT(DISTINCT i.candidate_reference_id) candidate_count ".$this->from()." WHERE $where GROUP BY classification ORDER BY classification");
        $q->execute($params);return $q->fetchAll();
    }

    public function supportWorkload(array $filter): array
    {
        [$where,$params]=$this->scope($filter);$q=$this->db->prepare("SELECT sp.id,sp.name,COUNT(*) tool_interviews,
            SUM(i.duration_minutes) scheduled_minutes,ROUND(AVG(i.duration_minutes),1) average_minutes
            ".$this->from()." WHERE $where AND i.status='BOOKED' AND i.interview_type='TOOL' AND sp.id IS NOT NULL
            GROUP BY sp.id,sp.name ORDER BY scheduled_minutes DESC,sp.name");
        $q->execute($params);return $q->fetchAll();
    }

    public function supportDirectory(array $filter): array
    {
        $params=[$filter['end_utc']];$where='created_at < ?';
        if($filter['support_person_id']){$where.=' AND id=?';$params[]=$filter['support_person_id'];}
        $q=$this->db->prepare("SELECT id,name,active,created_at FROM support_people WHERE $where ORDER BY name");$q->execute($params);return $q->fetchAll();
    }

    public function resourceBookings(array $filter): array
    {
        [$where,$params]=$this->scope($filter);$q=$this->db->prepare("SELECT r.id,r.name,r.resource_type,r.active,r.created_at,
            COUNT(*) booked_interviews,COALESCE(SUM(i.duration_minutes),0) booked_minutes
            ".$this->from()." WHERE $where AND i.status='BOOKED' GROUP BY r.id,r.name,r.resource_type,r.active,r.created_at ORDER BY r.name");
        $q->execute($params);return $q->fetchAll();
    }

    public function resourceDirectory(array $filter): array
    {
        $params=[$filter['end_utc']];$where='created_at < ?';
        if($filter['resource_id']){$where.=' AND id=?';$params[]=$filter['resource_id'];}
        $q=$this->db->prepare("SELECT id,name,resource_type,active,created_at FROM resources WHERE $where ORDER BY FIELD(resource_type,'TOOL','NORMAL'),name");$q->execute($params);return $q->fetchAll();
    }

    public function peakTimes(array $filter): array
    {
        [$where,$params]=$this->scope($filter);
        $sql="SELECT LPAD(FLOOR((HOUR(CONVERT_TZ(i.start_time,'+00:00',?))*60+MINUTE(CONVERT_TZ(i.start_time,'+00:00',?)))/30)*30 DIV 60,2,'0') hour_part,
            LPAD(MOD(FLOOR((HOUR(CONVERT_TZ(i.start_time,'+00:00',?))*60+MINUTE(CONVERT_TZ(i.start_time,'+00:00',?)))/30)*30,60),2,'0') minute_part,
            COUNT(*) interview_count ".$this->from()." WHERE $where AND i.status='BOOKED' GROUP BY hour_part,minute_part ORDER BY interview_count DESC,hour_part,minute_part LIMIT 12";
        $q=$this->db->prepare($sql);$q->execute(array_merge(array_fill(0,4,$filter['utc_offset']),$params));return $q->fetchAll();
    }

    public function cancellationSources(array $filter): array
    {
        [$where,$params]=$this->scope($filter);$q=$this->db->prepare("SELECT a.action,COUNT(DISTINCT a.entity_id) total FROM audit_logs a
            JOIN interviews i ON i.id=a.entity_id 
            LEFT JOIN users u ON u.id=i.candidate_id LEFT JOIN candidate_profiles cp ON cp.user_id=i.candidate_id JOIN technologies t ON t.id=i.technology_id
            JOIN resources r ON r.id=i.resource_id LEFT JOIN support_people sp ON sp.id=i.support_person_id
            WHERE $where AND i.status='CANCELLED' AND a.action IN ('booking.cancelled','admin.booking.cancelled') GROUP BY a.action");
        $q->execute($params);return $q->fetchAll();
    }

    public function exportStatement(array $filter): \PDOStatement
    {
        [$where,$params]=$this->scope($filter);$q=$this->db->prepare("SELECT i.id,COALESCE(u.full_name,i.candidate_name_snapshot) candidate_name,u.phone,COALESCE(cp.classification,i.candidate_classification_snapshot) classification,i.company_name,i.job_role,t.name technology_name,
            i.interview_round,i.interview_type,sp.name support_person_name,r.name resource_name,i.start_time,i.end_time,i.duration_minutes,
            i.hr_name,i.hr_phone,i.status,i.created_at ".$this->from()." WHERE $where ORDER BY i.start_time,i.id");
        $q->execute($params);return $q;
    }

    private function from(): string
    {
        return 'FROM interviews i LEFT JOIN users u ON u.id=i.candidate_id LEFT JOIN candidate_profiles cp ON cp.user_id=i.candidate_id JOIN technologies t ON t.id=i.technology_id JOIN resources r ON r.id=i.resource_id LEFT JOIN support_people sp ON sp.id=i.support_person_id';
    }

    private function scope(array $f): array
    {
        $where=['i.start_time>=?','i.start_time<?'];$params=[$f['start_utc'],$f['end_utc']];
        foreach(['technology_id'=>'i.technology_id','support_person_id'=>'i.support_person_id','resource_id'=>'i.resource_id'] as $key=>$column){if($f[$key]){$where[]="$column=?";$params[]=$f[$key];}}
        if($f['type']){$where[]='i.interview_type=?';$params[]=$f['type'];}
        if($f['classification']){$where[]='COALESCE(cp.classification,i.candidate_classification_snapshot)=?';$params[]=$f['classification'];}
        return [implode(' AND ',$where),$params];
    }
}
