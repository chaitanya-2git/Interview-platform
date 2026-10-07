<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Repositories\AdminRepository;
use App\Services\{AdminOperationsService,AuditService,AuthService};
use App\Support\{Database,Scheduling,Session,View};
use App\Validation\ValidationException;
use DateTimeImmutable;use DateTimeZone;

final class AdminDirectoryController
{
    public function resources():void{$this->resourcesPage();}
    public function saveResource():void{$user=(new AuthService())->user();try{(new AdminOperationsService(Database::connection(),new AuditService(Database::connection())))->saveResource((int)$user['id'],$_POST);Session::flash('Resource saved.');View::redirect('admin/resources');}catch(ValidationException $e){http_response_code(422);$this->resourcesPage($e->errors);}catch(\PDOException $e){if(($e->errorInfo[1]??null)!==1062)throw $e;http_response_code(422);$this->resourcesPage(['form'=>'A resource with this name already exists.']);}}
    private function resourcesPage(array $errors=[]):void{$s=new Scheduling();View::render('admin/resources',['title'=>'Resources','user'=>(new AuthService())->user(),'errors'=>$errors,'resources'=>(new AdminRepository(Database::connection()))->resources($s->hours()->nowUtc()->format('Y-m-d H:i:s'))]);}
    public function support():void{$this->supportPage();}
    public function saveSupport():void{$user=(new AuthService())->user();try{(new AdminOperationsService(Database::connection(),new AuditService(Database::connection())))->saveSupportPerson((int)$user['id'],$_POST);Session::flash('Support person saved.');View::redirect('admin/support');}catch(ValidationException $e){http_response_code(422);$this->supportPage($e->errors);}catch(\PDOException $e){if(($e->errorInfo[1]??null)!==1062)throw $e;http_response_code(422);$this->supportPage(['form'=>'A support person with this name already exists.']);}}
    private function supportPage(array $errors=[]):void{$s=new Scheduling();$h=$s->hours();$now=$h->nowUtc();$local=$now->setTimezone($h->timezone());$utc=new DateTimeZone('UTC');$from=$local->setTime(0,0)->setTimezone($utc)->format('Y-m-d H:i:s');$to=$local->modify('+1 day')->setTime(0,0)->setTimezone($utc)->format('Y-m-d H:i:s');View::render('admin/support',['title'=>'Support team','user'=>(new AuthService())->user(),'errors'=>$errors,'people'=>(new AdminRepository(Database::connection()))->supportPeople($from,$to,$now->format('Y-m-d H:i:s'))]);}
    public function candidates():void{$db=Database::connection();$filters=['search'=>trim((string)($_GET['search']??'')),'technology_id'=>(string)($_GET['technology_id']??''),'classification'=>(string)($_GET['classification']??''),'status'=>(string)($_GET['status']??'')];$page=max(1,(int)($_GET['page']??1));$result=(new AdminRepository($db))->candidates($filters,$page);View::render('admin/candidates',['title'=>'Candidates','user'=>(new AuthService())->user(),'filters'=>$filters,'result'=>$result,'technologies'=>$db->query('SELECT id,name FROM technologies ORDER BY name')->fetchAll()]);}
    public function candidate():void{$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$repo=new AdminRepository(Database::connection());$candidate=$id?$repo->candidate((int)$id):null;if(!$candidate){http_response_code(404);View::render('error',['title'=>'Candidate not found','message'=>'The requested candidate does not exist.','user'=>(new AuthService())->user()]);return;}$s=new Scheduling();$now=$s->hours()->nowUtc()->format('Y-m-d H:i:s');$ir=$s->repository();View::render('admin/candidate',['title'=>$candidate['full_name'],'user'=>(new AuthService())->user(),'candidate'=>$candidate,'historyCount'=>$repo->candidateInterviewCount((int)$id),'upcoming'=>$s->presenter()->many($ir->candidateUpcoming((int)$id,$now)),'past'=>$s->presenter()->many($ir->candidatePast((int)$id,$now)),'cancelled'=>$s->presenter()->many($ir->candidateCancelled((int)$id))]);}
}
