<?php
declare(strict_types=1);
namespace App\Repositories;

use PDO;

final class CandidateTechnologyRepository
{
    public function __construct(private readonly PDO $db) {}

    public function directory(bool $activeOnly=false):array
    {
        return (new TechnologyRepository($this->db))->picker($activeOnly);
    }

    public function stack(int $candidateId):array
    {
        return $this->stacks([$candidateId])[$candidateId]??[];
    }

    public function stacks(array $candidateIds):array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$candidateIds),static fn($id)=>$id>0)));
        if(!$ids)return [];
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $sql="SELECT x.candidate_id,t.id,t.name,t.active FROM (
            SELECT candidate_id,technology_id FROM candidate_technologies WHERE candidate_id IN ($marks)
            UNION
            SELECT user_id candidate_id,technology_id FROM candidate_profiles WHERE user_id IN ($marks) AND technology_id IS NOT NULL
        ) x JOIN technologies t ON t.id=x.technology_id ORDER BY x.candidate_id,t.name";
        $q=$this->db->prepare($sql);$q->execute([...$ids,...$ids]);$result=[];
        foreach($q as $row)$result[(int)$row['candidate_id']][]=['id'=>(int)$row['id'],'name'=>$row['name'],'active'=>(bool)$row['active']];
        return $result;
    }

    public function attach(array $rows):array
    {
        $stacks=$this->stacks(array_column($rows,'id'));
        foreach($rows as &$row){$row['tech_stack']=$stacks[(int)$row['id']]??[];$row['tech_stack_label']=$row['tech_stack']?implode(' · ',array_column($row['tech_stack'],'name')):'Tech stack not added yet';}
        unset($row);return $rows;
    }
}
