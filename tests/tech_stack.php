<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use App\Repositories\{AdminRepository,CandidateTechnologyRepository,SearchRepository,UserRepository};
use App\Services\{AuditService,AuthService,AvailabilityService,BookingService,BusinessHoursService,CandidateTechStackService,ReportService,ResourceAssignmentService};
use App\Support\{Config,Database,FrozenClock,Session};
use App\Validation\ValidationException;

if(Config::get('APP_ENV')!=='testing'||!str_contains(strtolower(Config::get('DB_DATABASE')),'test'))throw new RuntimeException('Tech Stack tests require a disposable testing database.');
$db=Database::connection();Session::start();$checks=0;
function tsok(bool $condition,string $message):void{global $checks;if(!$condition){fwrite(STDERR,'FAIL: '.$message.PHP_EOL);throw new RuntimeException('FAIL: '.$message);}$checks++;}
function tsreject(callable $operation,string $key):void{global $checks;try{$operation();}catch(ValidationException $e){tsok(isset($e->errors[$key]),'expected '.$key.' validation error');return;}throw new RuntimeException('Expected ValidationException for '.$key);}
function tsregister(array $technologies,string $name,string $phone):int{$_SERVER['REMOTE_ADDR']='127.0.1.'.random_int(2,240);(new AuthService())->register(['full_name'=>$name,'phone'=>$phone,'password'=>'TestingPassword12','classification'=>'Genuine','technology_ids'=>$technologies],null);return (int)Database::connection()->query('SELECT MAX(id) FROM users')->fetchColumn();}

$db->exec("INSERT INTO technologies(name,active) VALUES('Inactive Legacy Skill',0)");
$technologyIds=$db->query('SELECT name,id FROM technologies')->fetchAll(PDO::FETCH_KEY_PAIR);$java=(int)$technologyIds['Java'];$spring=(int)$technologyIds['Spring Boot'];$aws=(int)$technologyIds['AWS'];$inactive=(int)$technologyIds['Inactive Legacy Skill'];
$one=tsregister([(string)$java],'One Skill Candidate','+919811110001');$stackRepo=new CandidateTechnologyRepository($db);tsok(array_column($stackRepo->stack($one),'id')===[$java],'registration stores one technology in junction table');
$multi=tsregister([(string)$java,(string)$spring,(string)$aws],'Multi Skill Candidate','+919811110002');tsok(count($stackRepo->stack($multi))===3,'registration stores multiple technologies');
tsok($db->query("SELECT technology_id FROM candidate_profiles WHERE user_id=$multi")->fetchColumn()===null,'new profiles do not use the legacy single technology column');
$service=new CandidateTechStackService($db);$service->updateOwn($one,[$java,$java,$spring]);tsok(array_column($stackRepo->stack($one),'id')===[$java,$spring],'duplicate technology IDs are deduplicated');
tsreject(fn()=>$service->updateOwn($one,[$java,999999]),'technology_ids');
tsreject(fn()=>$service->updateOwn($one,[$java,$inactive]),'technology_ids');
$db->prepare('INSERT INTO candidate_technologies(candidate_id,technology_id) VALUES(?,?)')->execute([$one,$inactive]);$service->updateOwn($one,[$java,$spring,$inactive]);$historical=$stackRepo->stack($one);tsok((bool)array_filter($historical,fn($x)=>$x['id']===$inactive&&!$x['active']),'historical inactive skill remains displayable and retainable');
$routes=file_get_contents(ROOT.'/routes/web.php');$home=file_get_contents(ROOT.'/app/Controllers/HomeController.php');tsok(str_contains($routes,"'/profile/tech-stack'")&&str_contains($routes,"'CANDIDATE'")&&str_contains($home,"updateOwn((int)\$user['id']"),'candidate Tech Stack update is role and owner scoped');
$adminRepo=new AdminRepository($db);$directory=$adminRepo->candidates(['search'=>'Multi Skill','technology_id'=>'','classification'=>''],1,20);tsok(count($directory['rows'][0]['tech_stack'])===3,'admin directory loads multiple skills');
$filtered=$adminRepo->candidates(['search'=>'','technology_id'=>(string)$spring,'classification'=>''],1,20);$filteredIds=array_column($filtered['rows'],'id');tsok(in_array($one,$filteredIds,true)&&in_array($multi,$filteredIds,true),'candidate Tech Stack technology filter matches all qualified candidates');tsok(count($filteredIds)===count(array_unique($filteredIds)),'technology filter does not duplicate candidates');
$search=(new SearchRepository($db))->search('Spring Boot');tsok(count(array_filter($search['candidates'],fn($row)=>(int)$row['id']===$multi))===1,'global search finds candidate by Tech Stack');
$candidateBook=file_get_contents(ROOT.'/app/Views/candidate/book.php');tsok(str_contains($candidateBook,'Your Tech Stack')&&str_contains($candidateBook,'Other Technologies')&&str_contains($candidateBook,'Interview Technology'),'candidate booking prioritizes Tech Stack while retaining one interview technology');
$adminBook=file_get_contents(ROOT.'/app/Views/admin/booking.php');tsok(str_contains($adminBook,'data-tech-ids')&&str_contains($adminBook,'data-admin-technology'),'admin booking exposes candidate Tech Stack prioritization context');

$clock=new FrozenClock(new DateTimeImmutable('2034-01-02T02:30:00+00:00'));$hours=new BusinessHoursService($db,$clock);$availability=new AvailabilityService($db,$hours,new ResourceAssignmentService($db));$booking=new BookingService($db,$availability,$hours,new AuditService($db));
$input=['company_name'=>'Stack Report Co','job_role'=>'Engineer','technology_id'=>(string)$aws,'interview_round'=>'Technical','hr_availability'=>'unavailable','hr_name'=>'','hr_phone'=>'','interview_type'=>'NORMAL','support_person_id'=>'','duration'=>'30','date'=>'2034-01-03','time'=>'10:00'];$interview=$booking->create($multi,$input);tsok($interview>0&&(int)$db->query("SELECT technology_id FROM interviews WHERE id=$interview")->fetchColumn()===$aws,'booking still stores exactly one interview technology');
tsreject(fn()=>$booking->create($one,array_replace($input,['technology_id'=>''])),'technology_id');
$reports=new ReportService($db,$hours,$clock);$awsReport=$reports->build($reports->filters(['preset'=>'custom','start'=>'2034-01-03','end'=>'2034-01-03','technology_id'=>(string)$aws]));$javaReport=$reports->build($reports->filters(['preset'=>'custom','start'=>'2034-01-03','end'=>'2034-01-03','technology_id'=>(string)$java]));tsok((int)$awsReport['metrics']['total_interviews']===1&&(int)$javaReport['metrics']['total_interviews']===0,'reports continue to group and filter by interview technology');
$service->updateOwn($one,[]);$profile=(new UserRepository())->profile($one);tsok($profile['tech_stack']===[]&&$profile['tech_stack_label']==='Tech stack not added yet','empty Tech Stack has no General fallback');
$general=(int)$db->query("SELECT COUNT(*) FROM technologies WHERE LOWER(name)='general' AND active=1")->fetchColumn();tsok($general===0,'General remains unavailable for new Tech Stack selections');
$migration=file_get_contents(ROOT.'/database/migrations/003_candidate_tech_stack.sql');$generalMigration=file_get_contents(ROOT.'/database/migrations/004_deactivate_general_technology.sql');tsok(str_contains($migration,'INSERT IGNORE INTO candidate_technologies')&&str_contains($migration,"LOWER(TRIM(t.name)) <> 'general'")&&str_contains($migration,'MODIFY technology_id BIGINT UNSIGNED NULL')&&str_contains($generalMigration,"LOWER(TRIM(name)) = 'general'"),'migrations preserve meaningful legacy data, exclude General from stacks, and deactivate its directory row');
$columns=$db->query("SHOW COLUMNS FROM candidate_profiles LIKE 'technology_id'")->fetch();tsok($columns['Null']==='YES','legacy candidate profile technology column is nullable');
$indexes=$db->query("SHOW INDEX FROM candidate_technologies")->fetchAll();tsok(count(array_filter($indexes,fn($i)=>$i['Key_name']==='PRIMARY'))===2&&count(array_filter($indexes,fn($i)=>$i['Key_name']==='idx_candidate_technologies_technology_candidate'))===2,'junction table has unique and lookup indexes');
tsok(str_contains(file_get_contents(ROOT.'/app/Views/auth/register.php'),'tech-stack-picker.php')&&str_contains(file_get_contents(ROOT.'/public/assets/app.js'),'data-tech-picker'),'registration uses searchable tag picker');
echo $checks." Tech Stack checks passed.\n";
