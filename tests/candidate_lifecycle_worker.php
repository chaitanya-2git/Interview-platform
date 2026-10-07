<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\{AuditService,AvailabilityService,BookingService,BusinessHoursService,CandidateLifecycleService,ResourceAssignmentService};
use App\Support\{Database,FrozenClock};

$job=json_decode(base64_decode($argv[1]??'',true)?:'',true,flags:JSON_THROW_ON_ERROR);
while(microtime(true)<(float)$job['wait_until']){usleep(1000);}
$db=Database::connection();
try{
    if($job['action']==='delete'){
        (new CandidateLifecycleService($db,new AuditService($db)))->delete((int)$job['admin_id'],(int)$job['candidate_id'],'DELETE');
    }else{
        $clock=new FrozenClock(new DateTimeImmutable('2038-01-04T02:30:00+00:00'));
        $hours=new BusinessHoursService($db,$clock);
        $booking=new BookingService($db,new AvailabilityService($db,$hours,new ResourceAssignmentService($db)),$hours,new AuditService($db));
        $booking->createAsAdmin((int)$job['admin_id'],(int)$job['candidate_id'],['company_name'=>'Race Company','job_role'=>'Engineer','technology_id'=>(string)$job['technology_id'],'interview_round'=>'Technical','hr_availability'=>'unavailable','interview_type'=>'NORMAL','support_person_id'=>'','duration'=>'30','date'=>'2038-01-05','time'=>'10:00']);
    }
    echo json_encode(['ok'=>true]);
}catch(Throwable $error){echo json_encode(['ok'=>false,'type'=>get_class($error)]);}
