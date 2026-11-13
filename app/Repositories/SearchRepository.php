<?php
declare(strict_types=1);
namespace App\Repositories;

use PDO;

final class SearchRepository
{
    public function __construct(private readonly PDO $db){}
    public function search(string $term,int $limit=6):array
    {
        $experience=\App\Services\ExperienceQuery::parse($this->db,$term);
        if($experience!==null)return ['candidates'=>$this->byExperience($experience,10),'interviews'=>[],'experience'=>$experience];
        $limit=max(1,min(10,$limit));$safe=str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$term);$prefix=$safe.'%';$word='% '.$safe.'%';
        $q=$this->db->prepare("SELECT u.id,u.full_name,u.phone FROM users u JOIN candidate_profiles cp ON cp.user_id=u.id WHERE u.role='CANDIDATE' AND (u.full_name LIKE ? ESCAPE '\\\\' OR u.full_name LIKE ? ESCAPE '\\\\' OR u.phone LIKE ? ESCAPE '\\\\' OR EXISTS(SELECT 1 FROM candidate_technologies ct JOIN technologies st ON st.id=ct.technology_id WHERE ct.candidate_id=u.id AND (st.name LIKE ? ESCAPE '\\\\' OR st.name LIKE ? ESCAPE '\\\\')) OR EXISTS(SELECT 1 FROM technologies lt WHERE lt.id=cp.technology_id AND (lt.name LIKE ? ESCAPE '\\\\' OR lt.name LIKE ? ESCAPE '\\\\'))) ORDER BY u.full_name LIMIT $limit");
        $q->execute([$prefix,$word,$prefix,$prefix,$word,$prefix,$word]);$candidates=(new CandidateTechnologyRepository($this->db))->attach($q->fetchAll());
        $id=ctype_digit($term)?(int)$term:0;$q=$this->db->prepare("SELECT i.id,COALESCE(u.full_name,i.candidate_name_snapshot) candidate_name,i.company_name,i.start_time,i.status FROM interviews i LEFT JOIN users u ON u.id=i.candidate_id WHERE (i.company_name LIKE ? ESCAPE '\\\\' OR i.company_name LIKE ? ESCAPE '\\\\' OR COALESCE(u.full_name,i.candidate_name_snapshot) LIKE ? ESCAPE '\\\\' OR COALESCE(u.full_name,i.candidate_name_snapshot) LIKE ? ESCAPE '\\\\' OR i.id=?) ORDER BY i.start_time DESC LIMIT $limit");
        $q->execute([$prefix,$word,$prefix,$word,$id]);return ['candidates'=>$candidates,'interviews'=>$q->fetchAll()];
    }

    /** Candidates with at least N years in a skill (or N years total), most experienced first. */
    public function byExperience(array $experience,int $limit):array
    {
        if($experience['technology_ids']){
            $marks=implode(',',array_fill(0,count($experience['technology_ids']),'?'));
            $q=$this->db->prepare("SELECT u.id,u.full_name,u.phone,MAX(ct.years_experience) matched_years,SUBSTRING_INDEX(GROUP_CONCAT(t.name ORDER BY ct.years_experience DESC SEPARATOR '|'),'|',1) matched_skill
                FROM candidate_technologies ct JOIN technologies t ON t.id=ct.technology_id JOIN users u ON u.id=ct.candidate_id
                WHERE u.role='CANDIDATE' AND ct.technology_id IN ($marks) AND ct.years_experience>=?
                GROUP BY u.id,u.full_name,u.phone ORDER BY matched_years DESC,u.full_name LIMIT ".max(1,min(50,$limit)));
            $q->execute([...$experience['technology_ids'],$experience['min_years']]);
        }else{
            $q=$this->db->prepare("SELECT u.id,u.full_name,u.phone,p.experience_years matched_years,'Total experience' matched_skill FROM users u JOIN candidate_profiles p ON p.user_id=u.id
                WHERE u.role='CANDIDATE' AND p.experience_years>=? ORDER BY p.experience_years DESC,u.full_name LIMIT ".max(1,min(50,$limit)));
            $q->execute([$experience['min_years']]);
        }
        return (new CandidateTechnologyRepository($this->db))->attach($q->fetchAll());
    }
}
