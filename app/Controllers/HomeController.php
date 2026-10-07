<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Repositories\UserRepository;
use App\Repositories\AdminRepository;
use App\Services\{AuditService,AuthService,CandidateTechStackService,ResumeService};
use App\Support\{Database, Scheduling, Session, View};
use App\Validation\ValidationException;
final class HomeController
{
    public function index(): void
    {
        $user = (new AuthService())->user();
        View::redirect($user ? ($user['role'] === 'ADMIN' ? 'admin' : 'candidate') : 'login');
    }
    public function admin(): void
    {
        $scheduling=new Scheduling();$hours=$scheduling->hours();$now=$hours->nowUtc()->setTimezone($hours->timezone());$data=(new AdminRepository(Database::connection()))->dashboard($now);$data['rows']=$scheduling->presenter()->many($data['rows']);$data['current']=$data['current']?$scheduling->presenter()->one($data['current']):null;$data['next']=$data['next']?$scheduling->presenter()->one($data['next']):null;
        View::render('admin/home', $data+['title'=>'Operations dashboard','user'=>(new AuthService())->user(),'now'=>$now]);
    }
    public function candidate(): void
    {
        $user = (new AuthService())->user();
        $scheduling = new Scheduling(); $hours = $scheduling->hours(); $repository = $scheduling->repository(); $presenter = $scheduling->presenter();
        $now = $hours->nowUtc()->format('Y-m-d H:i:s');
        $upcoming = $presenter->many($repository->candidateUpcoming((int) $user['id'], $now, 4));
        View::render('candidate/home', ['title' => 'Your interview workspace', 'user' => $user, 'profile' => (new UserRepository())->profile((int) $user['id']),
            'nextInterview' => $upcoming[0] ?? null, 'upcoming' => $upcoming, 'past' => $presenter->many($repository->candidatePast((int) $user['id'], $now, 3))]);
    }
    public function profile(): void
    {
        $user = (new AuthService())->user();
        $this->profilePage($user);
    }
    public function updateTechStack():void
    {
        $user=(new AuthService())->user();try{(new CandidateTechStackService(Database::connection()))->updateOwn((int)$user['id'],$_POST['technology_ids']??[]);Session::flash('Your tech stack was updated.');View::redirect('profile');}catch(ValidationException $e){http_response_code(422);$this->profilePage($user,$e->errors,is_array($_POST['technology_ids']??null)?$_POST['technology_ids']:[]);}
    }
    public function updateResume():void
    {
        $user=(new AuthService())->user();$db=Database::connection();
        try{(new ResumeService($db,new AuditService($db)))->replace((int)$user['id'],(int)$user['id'],isset($_FILES['resume'])&&is_array($_FILES['resume'])?$_FILES['resume']:null);Session::flash('Your resume was uploaded securely.');View::redirect('profile');}
        catch(ValidationException $e){http_response_code(422);$this->profilePage($user,['resume'=>(string)($e->errors['resume']??'The resume could not be uploaded.')]);}
    }
    private function profilePage(array $user,array $errors=[],?array $selected=null):void
    {
        $repo=new UserRepository();$profile=$repo->profile((int)$user['id']);$technologies=$repo->technologies();$known=array_fill_keys(array_map('intval',array_column($technologies,'id')),true);foreach($profile['tech_stack'] as $item)if(!isset($known[$item['id']]))$technologies[]=$item;usort($technologies,static fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));View::render('candidate/profile',['title'=>'Your profile','user'=>$user,'profile'=>$profile,'errors'=>$errors,'technologies'=>$technologies,'selectedTechnologyIds'=>$selected??array_column($profile['tech_stack'],'id')]);
    }
    public function photo(): void
    {
        $user = (new AuthService())->user();
        $requested=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($user['role']==='ADMIN'){$candidateId=$requested?(int)$requested:0;}
        else{$candidateId=(int)$user['id'];if($requested&&((int)$requested!==$candidateId)){$this->missingPhoto();return;}}
        if($candidateId<1){$this->missingPhoto();return;}
        $query=Database::connection()->prepare("SELECT p.photo_filename FROM candidate_profiles p JOIN users u ON u.id=p.user_id WHERE p.user_id=? AND u.role='CANDIDATE'");
        $query->execute([$candidateId]);$name=(string)($query->fetchColumn()?:'');
        $directory=realpath(ROOT.'/storage/uploads');
        if($directory===false||!preg_match('/^[a-f0-9]{48}\.jpg$/',$name)){$this->missingPhoto();return;}
        $path=$directory.DIRECTORY_SEPARATOR.$name;
        if(!is_file($path)){$this->missingPhoto();return;}
        header('Content-Type: image/jpeg');
        header('Content-Length: '.filesize($path));
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }
    public function resume():void
    {
        $user=(new AuthService())->user();$requested=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($user['role']==='ADMIN'){$candidateId=$requested?(int)$requested:0;}
        else{$candidateId=(int)$user['id'];if($requested&&(int)$requested!==$candidateId){$this->missingResume();return;}}
        if($candidateId<1){$this->missingResume();return;}
        $db=Database::connection();$query=$db->prepare("SELECT r.* FROM candidate_resumes r JOIN users u ON u.id=r.candidate_id WHERE r.candidate_id=? AND u.role='CANDIDATE'");$query->execute([$candidateId]);$resume=$query->fetch();
        if(!$resume){$this->missingResume();return;}
        $service=new ResumeService($db,new AuditService($db));$path=$service->path((string)$resume['stored_filename']);if(!$path){$this->missingResume();return;}
        $download=(string)($_GET['download']??'')==='1'||$resume['mime_type']!=='application/pdf';$inline=!$download;
        if($user['role']==='ADMIN')(new AuditService($db))->record((int)$user['id'],$download?'admin.resume_downloaded':'admin.resume_viewed','user',$candidateId,['mime_type'=>$resume['mime_type'],'file_size'=>(int)$resume['file_size']]);
        header('Content-Type: '.$resume['mime_type']);header('Content-Length: '.filesize($path));header('Content-Disposition: '.$service->contentDisposition((string)$resume['original_filename'],$inline));header('Cache-Control: private, no-store, max-age=0');header('X-Content-Type-Options: nosniff');
        if($inline)header("Content-Security-Policy: sandbox");readfile($path);
    }
    private function missingPhoto():void{http_response_code(404);header('Cache-Control: no-store');}
    private function missingResume():void{http_response_code(404);header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');}
}
