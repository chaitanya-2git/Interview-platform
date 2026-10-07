<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\SearchRepository;
use App\Services\BusinessHoursService;
use App\Support\{Database,View};
use DateTimeImmutable;
use DateTimeZone;

final class AdminSearchController
{
    public function index():void
    {
        header('Content-Type: application/json; charset=utf-8');
        $q=trim(is_string($_GET['q']??null)?$_GET['q']:'');
        if(mb_strlen($q)<2){echo json_encode(['query'=>$q,'minimum'=>2,'candidates'=>[],'interviews'=>[]],JSON_UNESCAPED_UNICODE);return;}
        if(mb_strlen($q)>80)$q=mb_substr($q,0,80);
        $db=Database::connection();
        $results=(new SearchRepository($db))->search($q);
        $tz=(new BusinessHoursService($db))->timezone();
        $utc=new DateTimeZone('UTC');
        $payload=[
            'query'=>$q,
            'minimum'=>2,
            'candidates'=>array_map(static fn($r)=>[
                'label'=>$r['full_name'],
                'meta'=>($r['phone']??'No phone').' · '.$r['tech_stack_label'],
                'url'=>View::url('admin/candidate?id='.$r['id']),
            ],$results['candidates']),
            'interviews'=>array_map(static fn($r)=>[
                'label'=>'#'.$r['id'].' · '.$r['candidate_name'],
                'meta'=>$r['company_name'].' · '.(new DateTimeImmutable($r['start_time'],$utc))->setTimezone($tz)->format('d M Y, H:i'),
                'url'=>View::url('admin/interview?id='.$r['id']),
            ],$results['interviews']),
        ];
        echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}
