<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\TechnologyRepository;
use App\Services\{AuditService,AuthService};
use App\Support\{Database,Session,View};
use App\Validation\Input;

final class TechnologyController
{
    public function index():void{$this->page();}

    private function page(array $errors=[]):void
    {
        $db=Database::connection();$repo=new TechnologyRepository($db);$filters=['search'=>trim((string)($_GET['search']??'')),'category_id'=>(string)($_GET['category_id']??''),'status'=>(string)($_GET['status']??'')];$page=max(1,(int)($_GET['page']??1));
        View::render('admin/technologies',['title'=>'Technologies','user'=>(new AuthService())->user(),'errors'=>$errors,'filters'=>$filters,'result'=>$repo->paginated($filters,$page),'categories'=>$repo->categories()]);
    }

    public function save():void
    {
        $name=Input::text($_POST,'name');$id=filter_var($_POST['id']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);$active=Input::text($_POST,'active');$category=filter_var($_POST['category_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(mb_strlen($name)<2||mb_strlen($name)>100||preg_match('/[\x00-\x1f]/',$name)||$id===false||!in_array($active,['0','1'],true)||!$category){http_response_code(422);$this->page(['form'=>'Enter a technology name, category, and valid status.']);return;}
        if(mb_strtolower(trim($name))==='general'&&$active==='1'){http_response_code(422);$this->page(['form'=>'General must remain inactive. Add an explicit technology instead.']);return;}
        $db=Database::connection();
        try{
            $db->beginTransaction();$categoryQuery=$db->prepare('SELECT id FROM technology_categories WHERE id=?');$categoryQuery->execute([(int)$category]);if(!$categoryQuery->fetchColumn()){throw new \InvalidArgumentException('category');}$before=null;
            if($id>0){$query=$db->prepare('SELECT name,category_id,active FROM technologies WHERE id=? FOR UPDATE');$query->execute([$id]);$before=$query->fetch();if(!$before){$db->rollBack();http_response_code(404);$this->page(['form'=>'This technology no longer exists.']);return;}$db->prepare('UPDATE technologies SET name=?,category_id=?,active=? WHERE id=?')->execute([$name,(int)$category,(int)$active,$id]);}
            else{$query=$db->prepare('INSERT INTO technologies(name,category_id,active) VALUES(?,?,?)');$query->execute([$name,(int)$category,(int)$active]);$id=(int)$db->lastInsertId();}
            (new AuditService($db))->record((int)(new AuthService())->user()['id'],$before?'technology.updated':'technology.created','technology',$id,['before'=>$before,'after'=>['name'=>$name,'category_id'=>(int)$category,'active'=>(int)$active]]);$db->commit();
        }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();if($error instanceof \InvalidArgumentException){http_response_code(422);$this->page(['form'=>'Choose a valid technology category.']);return;}if($error instanceof \PDOException&&($error->errorInfo[1]??null)===1062){http_response_code(422);$this->page(['form'=>'A technology with this name already exists.']);return;}throw $error;}
        Session::flash('Technology saved. Candidate registration is up to date.');View::redirect('admin/technologies');
    }
}
