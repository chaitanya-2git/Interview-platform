<?php
declare(strict_types=1);
namespace App\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class AuditRepository
{
    public function __construct(private readonly PDO $db){}
    public function paginated(array $filters,DateTimeZone $timezone,int $page=1,int $perPage=30):array
    {
        [$where,$params]=$this->where($filters,$timezone);$from=' FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id WHERE '.implode(' AND ',$where);
        $count=$this->db->prepare('SELECT COUNT(*)'.$from);$count->execute($params);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=min(max(1,$page),$pages);$offset=($page-1)*$perPage;
        $q=$this->db->prepare('SELECT a.id,a.actor_id,a.action,a.entity_type,a.entity_id,a.created_at,u.full_name actor_name,u.role actor_role'.$from." ORDER BY a.created_at DESC,a.id DESC LIMIT $perPage OFFSET $offset");$q->execute($params);
        return ['rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    public function detail(int $id):?array{$q=$this->db->prepare('SELECT a.*,u.full_name actor_name,u.role actor_role FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id WHERE a.id=?');$q->execute([$id]);return $q->fetch()?:null;}
    public function actors():array{return $this->db->query('SELECT DISTINCT u.id,u.full_name FROM users u JOIN audit_logs a ON a.actor_id=u.id ORDER BY u.full_name')->fetchAll();}
    private function where(array $f,DateTimeZone $tz):array
    {
        $where=['1=1'];$params=[];$utc=new DateTimeZone('UTC');
        foreach(['date_from'=>false,'date_to'=>true] as $key=>$exclusive){$v=is_string($f[$key]??null)?$f[$key]:'';$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v,$tz);if($d&&$d->format('Y-m-d')===$v){$where[]='a.created_at'.($exclusive?'<?':'>=?');$params[]=($exclusive?$d->modify('+1 day'):$d)->setTimezone($utc)->format('Y-m-d H:i:s');}}
        $actor=filter_var($f['actor_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($actor){$where[]='a.actor_id=?';$params[]=$actor;}
        $category=is_string($f['category']??null)?$f['category']:'';$clauses=[
            'authentication'=>"a.action IN ('candidate.registered','admin.provisioned')",
            'booking'=>"a.action IN ('booking.created','admin.booking.created')",
            'rescheduling'=>"a.action IN ('booking.rescheduled','admin.booking.rescheduled')",
            'cancellation'=>"a.action IN ('booking.cancelled','admin.booking.cancelled')",
            'resource'=>"a.action LIKE 'resource.%'",'support'=>"a.action LIKE 'support_person.%'",
            'technology'=>"a.action LIKE 'technology.%'",'candidate'=>"a.action LIKE 'candidate.%'",'settings'=>"a.action LIKE 'settings.%'",
        ];if(isset($clauses[$category]))$where[]=$clauses[$category];
        return [$where,$params];
    }
}
