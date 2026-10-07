<?php
declare(strict_types=1);
namespace App\Repositories;

use PDO;

final class SearchRepository
{
    public function __construct(private readonly PDO $db){}
    public function search(string $term,int $limit=6):array
    {
        $limit=max(1,min(10,$limit));$safe=str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$term);$prefix=$safe.'%';$word='% '.$safe.'%';
        $q=$this->db->prepare("SELECT u.id,u.full_name,u.phone FROM users u JOIN candidate_profiles cp ON cp.user_id=u.id WHERE u.role='CANDIDATE' AND (u.full_name LIKE ? ESCAPE '\\\\' OR u.full_name LIKE ? ESCAPE '\\\\' OR u.phone LIKE ? ESCAPE '\\\\' OR EXISTS(SELECT 1 FROM candidate_technologies ct JOIN technologies st ON st.id=ct.technology_id WHERE ct.candidate_id=u.id AND (st.name LIKE ? ESCAPE '\\\\' OR st.name LIKE ? ESCAPE '\\\\')) OR EXISTS(SELECT 1 FROM technologies lt WHERE lt.id=cp.technology_id AND (lt.name LIKE ? ESCAPE '\\\\' OR lt.name LIKE ? ESCAPE '\\\\'))) ORDER BY u.full_name LIMIT $limit");
        $q->execute([$prefix,$word,$prefix,$prefix,$word,$prefix,$word]);$candidates=(new CandidateTechnologyRepository($this->db))->attach($q->fetchAll());
        $id=ctype_digit($term)?(int)$term:0;$q=$this->db->prepare("SELECT i.id,COALESCE(u.full_name,i.candidate_name_snapshot) candidate_name,i.company_name,i.start_time,i.status FROM interviews i LEFT JOIN users u ON u.id=i.candidate_id WHERE (i.company_name LIKE ? ESCAPE '\\\\' OR i.company_name LIKE ? ESCAPE '\\\\' OR COALESCE(u.full_name,i.candidate_name_snapshot) LIKE ? ESCAPE '\\\\' OR COALESCE(u.full_name,i.candidate_name_snapshot) LIKE ? ESCAPE '\\\\' OR i.id=?) ORDER BY i.start_time DESC LIMIT $limit");
        $q->execute([$prefix,$word,$prefix,$word,$id]);return ['candidates'=>$candidates,'interviews'=>$q->fetchAll()];
    }
}
