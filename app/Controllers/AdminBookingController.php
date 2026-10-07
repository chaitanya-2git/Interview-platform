<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Repositories\CandidateTechnologyRepository;
use App\Repositories\AdminRepository;
use App\Services\AuthService;
use App\Support\{Database,NotFoundException,Scheduling,Session,View};
use App\Validation\ValidationException;

final class AdminBookingController
{
    public function form():void{$this->page();}
    public function create():void{$user=(new AuthService())->user();$candidate=$this->id($_POST,'candidate_id');try{if(!$candidate)throw new ValidationException(['candidate_id'=>'Choose a candidate.']);$id=(new Scheduling())->booking()->createAsAdmin((int)$user['id'],$candidate,$_POST);Session::flash('Interview created and resource assigned.');View::redirect('admin/interview?id='.$id);}catch(ValidationException $e){http_response_code(422);$this->page($e->errors,$_POST);}}
    public function rescheduleForm():void{$this->page([],[], $this->id($_GET));}
    public function reschedule():void{$user=(new AuthService())->user();$id=$this->id($_POST);try{if(!$id)throw new NotFoundException();(new Scheduling())->booking()->rescheduleAsAdmin((int)$user['id'],$id,$_POST);Session::flash('Interview rescheduled. The resource assignment was recalculated.');View::redirect('admin/interview?id='.$id);}catch(NotFoundException){$this->missing($user);}catch(ValidationException $e){http_response_code(422);$this->page($e->errors,$_POST,$id);}}
    public function cancel():void{$user=(new AuthService())->user();$id=$this->id($_POST);try{if(!$id)throw new NotFoundException();(new Scheduling())->booking()->cancelAsAdmin((int)$user['id'],$id);Session::flash('Interview cancelled. Resource and support capacity were released.');View::redirect('admin/interviews');}catch(NotFoundException){$this->missing($user);}catch(ValidationException $e){Session::flash(reset($e->errors));View::redirect('admin/interview?id='.$id);}}
    private function page(array $errors=[],array $old=[],?int $interviewId=null):void{$db=Database::connection();$s=new Scheduling();$interview=$interviewId?$s->repository()->adminDetail($interviewId):null;if($interviewId&&!$interview){$this->missing((new AuthService())->user());return;}if($interview&&!$old){$p=$s->presenter()->one($interview);$old=$interview+['date'=>$p['date_value'],'time'=>$p['time_value'],'duration'=>$interview['duration_minutes'],'hr_availability'=>$interview['hr_unavailable']?'unavailable':'available'];}elseif(!$interview&&!$old){$old=['candidate_id'=>(string)($_GET['candidate_id']??''),'date'=>(string)($_GET['date']??'')];}
        $technologyRepo=new CandidateTechnologyRepository($db);$candidateRows=$db->query("SELECT u.id,u.full_name,u.phone FROM users u JOIN candidate_profiles p ON p.user_id=u.id WHERE u.role='CANDIDATE' AND u.active=1 ORDER BY u.full_name")->fetchAll();View::render('admin/booking',['title'=>$interview?'Reschedule interview':'New interview','user'=>(new AuthService())->user(),'errors'=>$errors,'old'=>$old,'interview'=>$interview,'candidates'=>$technologyRepo->attach($candidateRows),'technologies'=>$technologyRepo->directory(true),'supportPeople'=>$db->query('SELECT id,name FROM support_people WHERE active=1 ORDER BY name')->fetchAll()]);}
    private function id(array $source,string $key='id'):?int{$v=filter_var($source[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return $v?(int)$v:null;}
    private function missing(?array $user):void{http_response_code(404);View::render('error',['title'=>'Interview not found','message'=>'The requested interview does not exist.','user'=>$user]);}
}
