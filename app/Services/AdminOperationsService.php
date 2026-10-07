<?php
declare(strict_types=1);
namespace App\Services;

use App\Validation\ValidationException;
use PDO;

final class AdminOperationsService
{
    public function __construct(private readonly PDO $db, private readonly AuditService $audit) {}

    public function saveResource(int $actorId, array $input): int { return $this->saveDirectory($actorId,$input,'resources','resource_type',['TOOL','NORMAL'],'resource'); }
    public function saveSupportPerson(int $actorId, array $input): int { return $this->saveDirectory($actorId,$input,'support_people',null,[],'support_person'); }

    private function saveDirectory(int $actorId,array $input,string $table,?string $typeColumn,array $types,string $entity): int
    {
        $id=filter_var($input['id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);$name=trim((string)($input['name']??''));$active=(string)($input['active']??'1');$type=strtoupper(trim((string)($input['type']??'')));
        $errors=[];if($id===false)$errors['form']='Invalid record.';if(mb_strlen($name)<2||mb_strlen($name)>120||preg_match('/[\x00-\x1f]/',$name))$errors['name']='Enter a name between 2 and 120 characters.';if(!in_array($active,['0','1'],true))$errors['active']='Choose a valid status.';if($typeColumn&&!in_array($type,$types,true))$errors['type']='Choose Tool or Normal.';if($errors)throw new ValidationException($errors);
        return $this->transaction(function()use($actorId,$id,$name,$active,$type,$table,$typeColumn,$entity){
            $this->lock();$before=null;
            if($id>0){$q=$this->db->prepare("SELECT * FROM $table WHERE id=? FOR UPDATE");$q->execute([$id]);$before=$q->fetch();if(!$before)throw new ValidationException(['form'=>'This record no longer exists.']);
                $unsafeDeactivation=$active==='0'&&(int)$before['active']===1;$unsafeTypeChange=$typeColumn&&$before[$typeColumn]!==$type;
                if($unsafeDeactivation||$unsafeTypeChange){$fk=$entity==='resource'?'resource_id':'support_person_id';$q=$this->db->prepare("SELECT COUNT(*) FROM interviews WHERE $fk=? AND status='BOOKED' AND end_time>UTC_TIMESTAMP()");$q->execute([$id]);$count=(int)$q->fetchColumn();if($count>0)throw new ValidationException(['form'=>'This '.str_replace('_',' ',$entity).' has '.$count.' upcoming interview'.($count===1?'':'s').'. Move or cancel them before changing its scheduling state.']);}
                $sql="UPDATE $table SET name=?,active=?".($typeColumn?",$typeColumn=?":'')." WHERE id=?";$args=[$name,(int)$active];if($typeColumn)$args[]=$type;$args[]=$id;$this->db->prepare($sql)->execute($args);
            }else{$sql="INSERT INTO $table (name,active".($typeColumn?",$typeColumn":'').") VALUES (?,?".($typeColumn?',?':'').")";$args=[$name,(int)$active];if($typeColumn)$args[]=$type;$this->db->prepare($sql)->execute($args);$id=(int)$this->db->lastInsertId();}
            $after=['name'=>$name,'active'=>(int)$active]+($typeColumn?['type'=>$type]:[]);$this->audit->record($actorId,$before?"$entity.updated":"$entity.created",$entity,$id,['before'=>$before,'after'=>$after]);return $id;
        });
    }

    public function saveSettings(int $actorId,array $input): void
    {
        $advance=filter_var($input['advance_minutes']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>10080]]);$cutoff=filter_var($input['cutoff_minutes']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>10080]]);$hours=[];$errors=[];$openInput=is_array($input['open']??null)?$input['open']:[];$opensInput=is_array($input['opens']??null)?$input['opens']:[];$closesInput=is_array($input['closes']??null)?$input['closes']:[];
        if($advance===false)$errors['advance_minutes']='Enter 0 to 10,080 minutes.';if($cutoff===false)$errors['cutoff_minutes']='Enter 0 to 10,080 minutes.';
        for($day=1;$day<=7;$day++){ $open=isset($openInput[$day])?1:0;$from=is_string($opensInput[$day]??null)?$opensInput[$day]:'';$to=is_string($closesInput[$day]??null)?$closesInput[$day]:'';if(!preg_match('/^\d{2}:\d{2}$/',$from)||!preg_match('/^\d{2}:\d{2}$/',$to)||$from>=$to)$errors['hours']='Every day needs valid opening and closing times, with closing after opening.';$hours[$day]=[$open,$from,$to]; }
        if($errors)throw new ValidationException($errors);
        $this->transaction(function()use($actorId,$advance,$cutoff,$hours){$this->lock();$before=['settings'=>$this->db->query("SELECT setting_key,setting_value FROM app_settings WHERE setting_key IN ('advance_booking_minutes','candidate_change_cutoff_minutes')")->fetchAll(PDO::FETCH_KEY_PAIR),'hours'=>$this->db->query('SELECT weekday,is_open,opens_at,closes_at FROM business_hours ORDER BY weekday')->fetchAll()];
            $future=$this->db->query("SELECT id,start_time,end_time FROM interviews WHERE status='BOOKED' AND end_time>UTC_TIMESTAMP() FOR UPDATE")->fetchAll();$zone=$this->db->query("SELECT setting_value FROM app_settings WHERE setting_key='business_timezone'")->fetchColumn();$tz=new \DateTimeZone(is_string($zone)&&$zone!==''?$zone:'Asia/Kolkata');$conflicts=[];
            foreach($future as $row){$start=(new \DateTimeImmutable($row['start_time'],new \DateTimeZone('UTC')))->setTimezone($tz);$end=(new \DateTimeImmutable($row['end_time'],new \DateTimeZone('UTC')))->setTimezone($tz);[$isOpen,$from,$to]=$hours[(int)$start->format('N')];if(!$isOpen||$start->format('H:i')<$from||$end->format('H:i')>$to)$conflicts[]=(int)$row['id'];}
            if($conflicts)throw new ValidationException(['form'=>'These hours would invalidate '.count($conflicts).' upcoming interview'.(count($conflicts)===1?'':'s').': #'.implode(', #',array_slice($conflicts,0,8)).'. Move or cancel them first.']);
            $q=$this->db->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");$q->execute(['advance_booking_minutes',(string)$advance]);$q->execute(['candidate_change_cutoff_minutes',(string)$cutoff]);$q=$this->db->prepare('UPDATE business_hours SET is_open=?,opens_at=?,closes_at=? WHERE weekday=?');foreach($hours as $day=>[$open,$from,$to])$q->execute([$open,$from,$to,$day]);$this->audit->record($actorId,'settings.scheduling.updated','settings',null,['before'=>$before,'after'=>['advance_minutes'=>$advance,'cutoff_minutes'=>$cutoff,'hours'=>$hours]]);
        });
    }
    private function lock():void{if(!$this->db->query('SELECT id FROM scheduling_mutex WHERE id=1 FOR UPDATE')->fetchColumn())throw new \RuntimeException('Scheduling mutex is not configured');}
    private function transaction(callable $fn):mixed{$this->db->beginTransaction();try{$v=$fn();$this->db->commit();return $v;}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
}
