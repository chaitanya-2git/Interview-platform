<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use App\Support\{Config,Database};

if (Config::get('APP_ENV') !== 'testing' || !str_contains(strtolower(Config::get('DB_DATABASE')), 'test')) {
    throw new RuntimeException('Visual fixture requires a disposable testing database.');
}
$db=Database::connection();
$candidate=(int)$db->query("SELECT id FROM users WHERE phone='+919711111111'")->fetchColumn();
if(!$candidate){throw new RuntimeException('Run the lifecycle HTTP fixture before visual QA.');}
$snapshot=$db->query("SELECT u.full_name,p.classification FROM users u JOIN candidate_profiles p ON p.user_id=u.id WHERE u.id=$candidate")->fetch();
$normal=(int)$db->query("SELECT id FROM resources WHERE resource_type='NORMAL' ORDER BY id LIMIT 1")->fetchColumn();
$tool=(int)$db->query("SELECT id FROM resources WHERE resource_type='TOOL' ORDER BY id LIMIT 1")->fetchColumn();
$support=(int)$db->query('SELECT id FROM support_people ORDER BY id LIMIT 1')->fetchColumn();
$technology=(int)$db->query("SELECT technology_id FROM candidate_technologies WHERE candidate_id=$candidate LIMIT 1")->fetchColumn();
$db->exec("DELETE FROM interviews WHERE company_name LIKE 'Visual QA %'");
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
$slots=[[-10,20,'NORMAL',$normal,null],[40,70,'TOOL',$tool,$support],[100,130,'NORMAL',$normal,null]];
$ids=[];
foreach($slots as $index=>$slot){
    $start=$now->modify(($slot[0]>=0?'+':'').$slot[0].' minutes');
    $end=$now->modify('+'.$slot[1].' minutes');
    $query=$db->prepare("INSERT INTO interviews
        (candidate_id,candidate_reference_id,candidate_name_snapshot,candidate_classification_snapshot,resource_id,support_person_id,technology_id,company_name,job_role,interview_round,hr_unavailable,duration_minutes,interview_type,start_time,end_time,status)
        VALUES(?,?,?,?,?,?,?,?,'Senior Engineer','Technical',1,30,?,?,?,'BOOKED')");
    $query->execute([$candidate,$candidate,$snapshot['full_name'],$snapshot['classification'],$slot[3],$slot[4],$technology,'Visual QA '.($index+1),$slot[2],$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s')]);
    $ids[]=(int)$db->lastInsertId();
}
echo json_encode(['candidate'=>$candidate,'interviews'=>$ids],JSON_THROW_ON_ERROR).PHP_EOL;
