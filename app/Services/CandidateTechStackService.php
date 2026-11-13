<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\CandidateTechnologyRepository;
use App\Validation\ValidationException;
use PDO;

final class CandidateTechStackService
{
    public const MAX_SKILLS=20;
    public function __construct(private readonly PDO $db,private readonly ?AuditService $audit=null){}

    public function validate(mixed $value,array $existingIds=[],bool $required=true,bool $locking=false):array
    {
        if(!is_array($value))throw new ValidationException(['technology_ids'=>'Choose your tech stack from the available technologies.']);
        $ids=[];
        foreach($value as $raw){
            if(!(is_int($raw)||(is_string($raw)&&ctype_digit($raw)))||(int)$raw<1)throw new ValidationException(['technology_ids'=>'The selected tech stack contains an invalid technology.']);
            $ids[]=(int)$raw;
        }
        $ids=array_values(array_unique($ids));
        if($required&&!$ids)throw new ValidationException(['technology_ids'=>'Select at least one technology for your tech stack.']);
        if(count($ids)>self::MAX_SKILLS)throw new ValidationException(['technology_ids'=>'Select no more than '.self::MAX_SKILLS.' technologies.']);
        if(!$ids)return [];
        $marks=implode(',',array_fill(0,count($ids),'?'));$q=$this->db->prepare("SELECT id,active FROM technologies WHERE id IN ($marks)".($locking?' LOCK IN SHARE MODE':''));$q->execute($ids);$found=[];foreach($q as $row)$found[(int)$row['id']]=(bool)$row['active'];
        $existing=array_fill_keys(array_map('intval',$existingIds),true);
        foreach($ids as $id){if(!array_key_exists($id,$found))throw new ValidationException(['technology_ids'=>'A selected technology no longer exists.']);if(!$found[$id]&&!isset($existing[$id]))throw new ValidationException(['technology_ids'=>'Inactive technologies cannot be newly added.']);}
        return $ids;
    }

    /** Years of experience per selected technology: technology_years[<id>] = 0–50 in steps of 0.5; blank = not stated. @return array<int,?float> */
    public function years(mixed $value,array $ids):array
    {
        $years=[];$value=is_array($value)?$value:[];
        foreach($ids as $id){
            $raw=$value[$id]??$value[(string)$id]??null;
            if($raw===null||(is_string($raw)&&trim($raw)==='')){$years[$id]=null;continue;}
            if(!is_scalar($raw)||!is_numeric(trim((string)$raw)))throw new ValidationException(['technology_ids'=>'Enter years of experience as a number, for example 2 or 1.5.']);
            $number=round((float)$raw*2)/2;
            if($number<0||$number>50)throw new ValidationException(['technology_ids'=>'Years of experience must be between 0 and 50.']);
            $years[$id]=$number;
        }
        return $years;
    }

    public static function totalYears(mixed $raw,bool $required=true):?float
    {
        if($raw===null||(is_string($raw)&&trim($raw)===''))return $required?throw new ValidationException(['experience_years'=>'Enter your total years of experience (0 if you are a fresher).']):null;
        if(!is_scalar($raw)||!is_numeric(trim((string)$raw)))throw new ValidationException(['experience_years'=>'Enter total experience as a number, for example 2 or 3.5.']);
        $number=round((float)$raw*2)/2;
        if($number<0||$number>50)throw new ValidationException(['experience_years'=>'Total experience must be between 0 and 50 years.']);
        return $number;
    }

    public function replace(int $candidateId,array $ids,array $years=[]):void
    {
        $this->db->prepare('DELETE FROM candidate_technologies WHERE candidate_id=?')->execute([$candidateId]);
        $insert=$this->db->prepare('INSERT INTO candidate_technologies(candidate_id,technology_id,years_experience) VALUES(?,?,?)');foreach($ids as $id)$insert->execute([$candidateId,$id,$years[$id]??null]);
        $this->db->prepare('UPDATE candidate_profiles SET technology_id=NULL WHERE user_id=?')->execute([$candidateId]);
    }

    public function updateOwn(int $candidateId,mixed $value,mixed $yearsValue=null,mixed $totalYears=null):void
    {
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare("SELECT p.user_id FROM candidate_profiles p JOIN users u ON u.id=p.user_id WHERE p.user_id=? AND u.role='CANDIDATE' FOR UPDATE");$q->execute([$candidateId]);if(!$q->fetchColumn())throw new ValidationException(['technology_ids'=>'Candidate profile not found.']);
            $current=array_column((new CandidateTechnologyRepository($this->db))->stack($candidateId),'id');$ids=$this->validate($value,$current,false,true);$years=$this->years($yearsValue,$ids);$total=self::totalYears($totalYears,false);$this->replace($candidateId,$ids,$years);$this->db->prepare('UPDATE candidate_profiles SET experience_years=? WHERE user_id=?')->execute([$total,$candidateId]);
            ($this->audit??new AuditService($this->db))->record($candidateId,'candidate.tech_stack.updated','user',$candidateId,['before'=>$current,'after'=>$ids,'years'=>$years,'experience_years'=>$total]);
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
