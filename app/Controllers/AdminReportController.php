<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\{AuthService,BusinessHoursService,ReportService};
use App\Support\{Database,View};
use DateTimeImmutable;
use DateTimeZone;

final class AdminReportController
{
    public function index():void
    {
        $db=Database::connection();$hours=new BusinessHoursService($db);$service=new ReportService($db,$hours);$filters=$service->filters($_GET);$report=$service->build($filters);
        View::render('admin/reports',['title'=>'Reports','user'=>(new AuthService())->user(),'filters'=>$filters,'query'=>$service->query($filters),'report'=>$report]+$this->directories($db));
    }
    public function export():never
    {
        $db=Database::connection();$hours=new BusinessHoursService($db);$service=new ReportService($db,$hours);$filters=$service->filters($_GET);
        if($filters['error']!==''){http_response_code(422);header('Content-Type: text/plain; charset=utf-8');echo $filters['error'];exit;}
        $name='blujay-interviews-'.$filters['start'].'-to-'.$filters['end'].'.csv';header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$name.'"');header('X-Content-Type-Options: nosniff');echo "\xEF\xBB\xBF";$out=fopen('php://output','wb');
        fputcsv($out,['Interview ID','Candidate Name','Phone','Candidate Classification','Company','Job Role','Technology','Round','Interview Type','Support Person','Resource','Date','Start Time','End Time','Duration Minutes','HR Name','HR Phone','Booking State','Created At']);$tz=$hours->timezone();$utc=new DateTimeZone('UTC');
        foreach($service->exportRows($filters) as $r){$start=(new DateTimeImmutable($r['start_time'],$utc))->setTimezone($tz);$end=(new DateTimeImmutable($r['end_time'],$utc))->setTimezone($tz);$created=(new DateTimeImmutable($r['created_at'],$utc))->setTimezone($tz);$cells=[$r['id'],$r['candidate_name'],$r['phone'],$r['classification'],$r['company_name'],$r['job_role'],$r['technology_name'],$r['interview_round'],$r['interview_type'],$r['support_person_name']??'',$r['resource_name'],$start->format('Y-m-d'),$start->format('H:i'),$end->format('H:i'),$r['duration_minutes'],$r['hr_name']??'',$r['hr_phone']??'',$r['status'],$created->format('Y-m-d H:i:s')];fputcsv($out,array_map([ReportService::class,'csvCell'],$cells));}
        fclose($out);exit;
    }
    private function directories(\PDO $db):array{return ['technologies'=>$db->query('SELECT id,name FROM technologies ORDER BY name')->fetchAll(),'supportPeople'=>$db->query('SELECT id,name FROM support_people ORDER BY name')->fetchAll(),'resources'=>$db->query('SELECT id,name FROM resources ORDER BY name')->fetchAll()];}
}
