<?php
declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

final class AuditPresenter
{
    private const LABELS=['candidate.registered'=>'Candidate registered','candidate.tech_stack.updated'=>'Candidate tech stack updated','candidate.deactivated'=>'Candidate deactivated','candidate.reactivated'=>'Candidate reactivated','candidate.deleted'=>'Candidate permanently deleted','admin.provisioned'=>'Administrator provisioned','booking.created'=>'Interview created','admin.booking.created'=>'Interview created','booking.rescheduled'=>'Interview rescheduled','admin.booking.rescheduled'=>'Interview rescheduled','booking.cancelled'=>'Interview cancelled','admin.booking.cancelled'=>'Interview cancelled','admin.cutoff_override'=>'Booking cutoff overridden','resource.created'=>'Resource created','resource.updated'=>'Resource updated','support_person.created'=>'Support person created','support_person.updated'=>'Support person updated','technology.created'=>'Technology created','technology.updated'=>'Technology updated','settings.scheduling.updated'=>'Scheduling settings updated'];
    public function __construct(private readonly DateTimeZone $timezone){}
    public function one(array $row,bool $withDetails=false):array
    {
        $row['label']=self::LABELS[$row['action']]??ucfirst(str_replace(['.','_'],' ',$row['action']));$row['created_local']=(new DateTimeImmutable($row['created_at'],new DateTimeZone('UTC')))->setTimezone($this->timezone);
        $entity=ucfirst(str_replace('_',' ',$row['entity_type']));$row['entity_label']=$entity.($row['entity_id']?' #'.$row['entity_id']:'');$row['summary']=$row['label'].' for '.strtolower($row['entity_label']).'.';
        if($withDetails){$decoded=json_decode((string)($row['metadata_json']??''),true);$row['details']=$this->redact(is_array($decoded)?$decoded:[]);unset($row['metadata_json']);}
        return $row;
    }
    public function many(array $rows):array{return array_map(fn($row)=>$this->one($row),$rows);}
    public function redact(mixed $value,?string $key=null):mixed
    {
        if($key!==null&&preg_match('/password|passwd|hash|session|csrf|token|secret|credential|db_password|api_key/i',$key))return '[redacted]';
        if(!is_array($value))return is_scalar($value)||$value===null?$value:'[unsupported value]';$clean=[];foreach($value as $k=>$v)$clean[$k]=$this->redact($v,(string)$k);return $clean;
    }
}
