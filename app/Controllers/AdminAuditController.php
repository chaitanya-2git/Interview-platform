<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\AuditRepository;
use App\Services\{AuditPresenter,AuthService,BusinessHoursService};
use App\Support\{Database,View};

final class AdminAuditController
{
    public function index():void
    {
        $db=Database::connection();$filters=[];foreach(['date_from','date_to','actor_id','category'] as $key)$filters[$key]=is_string($_GET[$key]??null)?trim($_GET[$key]):'';$repo=new AuditRepository($db);$page=max(1,(int)($_GET['page']??1));$tz=(new BusinessHoursService($db))->timezone();$result=$repo->paginated($filters,$tz,$page);$result['rows']=(new AuditPresenter($tz))->many($result['rows']);
        View::render('admin/audit',['title'=>'Audit log','user'=>(new AuthService())->user(),'filters'=>$filters,'result'=>$result,'actors'=>$repo->actors()]);
    }
    public function detail():void
    {
        $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$db=Database::connection();$row=$id?(new AuditRepository($db))->detail((int)$id):null;if(!$row){http_response_code(404);View::render('error',['title'=>'Audit event not found','message'=>'The requested activity record does not exist.','user'=>(new AuthService())->user()]);return;}$event=(new AuditPresenter((new BusinessHoursService($db))->timezone()))->one($row,true);View::render('admin/audit-detail',['title'=>$event['label'],'user'=>(new AuthService())->user(),'event'=>$event]);
    }
}
