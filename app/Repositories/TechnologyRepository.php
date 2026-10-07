<?php
declare(strict_types=1);
namespace App\Repositories;

use PDO;

final class TechnologyRepository
{
    public function __construct(private readonly PDO $db){}

    public function categories():array
    {
        return $this->db->query('SELECT id,name,slug FROM technology_categories ORDER BY sort_order,name')->fetchAll();
    }

    public function picker(bool $activeOnly=true):array
    {
        $sql="SELECT t.id,t.name,t.active,c.name category_name,COALESCE(GROUP_CONCAT(a.alias ORDER BY a.alias SEPARATOR ' | '),'') aliases
            FROM technologies t LEFT JOIN technology_categories c ON c.id=t.category_id LEFT JOIN technology_aliases a ON a.technology_id=t.id".($activeOnly?' WHERE t.active=1':'')." GROUP BY t.id,t.name,t.active,c.name,c.sort_order ORDER BY c.sort_order,c.name,t.name";
        return $this->db->query($sql)->fetchAll();
    }

    public function paginated(array $filters,int $page=1,int $perPage=25):array
    {
        $where=['1=1'];$params=[];$search=trim((string)($filters['search']??''));
        if($search!==''){$term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],mb_substr($search,0,100)).'%';$where[]="(t.name LIKE ? ESCAPE '\\\\' OR EXISTS(SELECT 1 FROM technology_aliases sa WHERE sa.technology_id=t.id AND sa.alias LIKE ? ESCAPE '\\\\'))";array_push($params,$term,$term);}
        $category=filter_var($filters['category_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($category){$where[]='t.category_id=?';$params[]=(int)$category;}
        $status=(string)($filters['status']??'');if(in_array($status,['active','inactive'],true)){$where[]='t.active=?';$params[]=$status==='active'?1:0;}
        $whereSql=implode(' AND ',$where);$count=$this->db->prepare("SELECT COUNT(*) FROM technologies t WHERE $whereSql");$count->execute($params);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=min(max(1,$page),$pages);$offset=($page-1)*$perPage;
        $sql="SELECT t.id,t.name,t.active,t.category_id,c.name category_name,
            (SELECT COUNT(DISTINCT x.candidate_id) FROM (SELECT candidate_id,technology_id FROM candidate_technologies UNION SELECT user_id candidate_id,technology_id FROM candidate_profiles WHERE technology_id IS NOT NULL) x WHERE x.technology_id=t.id) candidate_count,
            (SELECT COUNT(*) FROM interviews i WHERE i.technology_id=t.id) interview_count
            FROM technologies t LEFT JOIN technology_categories c ON c.id=t.category_id WHERE $whereSql ORDER BY c.sort_order,c.name,t.name LIMIT $perPage OFFSET $offset";
        $query=$this->db->prepare($sql);$query->execute($params);return ['rows'=>$query->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
}
