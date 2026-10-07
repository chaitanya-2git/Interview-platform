<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Repositories\AdminRepository;
use App\Services\AuthService;
use App\Support\{Database,Scheduling,View};
use DateTimeImmutable;

final class AdminCalendarController
{
    public function index(): void
    {
        $scheduling=new Scheduling();$tz=$scheduling->hours()->timezone();$today=$scheduling->hours()->nowUtc()->setTimezone($tz);$date=(string)($_GET['date']??$today->format('Y-m-d'));$selected=DateTimeImmutable::createFromFormat('!Y-m-d',$date,$tz);if(!$selected||$selected->format('Y-m-d')!==$date)$selected=$today->setTime(0,0);
        $view=($_GET['view']??'day')==='week'?'week':'day';if($view==='week'){$from=$selected->modify('monday this week');$to=$from->modify('+7 days');}else{$from=$selected;$to=$from->modify('+1 day');}
        $db=Database::connection();$repo=new AdminRepository($db);$hours=$scheduling->hours()->hoursForDate($selected);$rows=$scheduling->presenter()->many($repo->calendar($from,$to));$weekHours=[];foreach($db->query('SELECT weekday,is_open FROM business_hours')->fetchAll() as $row){$weekHours[(int)$row['weekday']]=(bool)$row['is_open'];}
        View::render('admin/calendar',['title'=>'Operations calendar','user'=>(new AuthService())->user(),'view'=>$view,'selected'=>$selected,'from'=>$from,'to'=>$to,'today'=>$today,'hours'=>$hours,'weekHours'=>$weekHours,'resources'=>$repo->activeResources(),'interviews'=>$rows]);
    }
}
