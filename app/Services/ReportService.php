<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\ReportRepository;
use App\Support\{Clock,SystemClock};
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class ReportService
{
    public function __construct(private readonly PDO $db,private readonly BusinessHoursService $hours,private readonly Clock $clock=new SystemClock()){}

    public function filters(array $input): array
    {
        $tz=$this->hours->timezone();$today=$this->clock->now($tz)->setTime(0,0);$preset=is_string($input['preset']??null)?$input['preset']:'this_month';
        $allowed=['today','yesterday','this_week','last_7_days','this_month','last_30_days','custom'];if(!in_array($preset,$allowed,true))$preset='this_month';
        [$start,$end]=match($preset){
            'today'=>[$today,$today], 'yesterday'=>[$today->modify('-1 day'),$today->modify('-1 day')],
            'this_week'=>[$today->modify('monday this week'),$today], 'last_7_days'=>[$today->modify('-6 days'),$today],
            'last_30_days'=>[$today->modify('-29 days'),$today],
            default=>[$today->modify('first day of this month'),$today],
        };
        $error='';
        if($preset==='custom'){
            $customStart=$this->date((string)($input['start']??''),$tz);$customEnd=$this->date((string)($input['end']??''),$tz);
            if(!$customStart||!$customEnd||$customStart>$customEnd||$customStart->diff($customEnd)->days>3660){$error='Choose a valid custom range of up to 10 years.';$preset='this_month';$start=$today->modify('first day of this month');$end=$today;}else{[$start,$end]=[$customStart,$customEnd];}
        }
        $type=strtoupper(trim(is_string($input['type']??null)?$input['type']:''));if(!in_array($type,['TOOL','NORMAL'],true))$type='';
        $classification=trim(is_string($input['classification']??null)?$input['classification']:'');if(!in_array($classification,['Genuine','Fake'],true))$classification='';
        $ids=[];foreach(['technology_id','support_person_id','resource_id'] as $key){$id=filter_var($input[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$ids[$key]=$id?(int)$id:null;}
        $endExclusive=$end->modify('+1 day');$utc=new DateTimeZone('UTC');
        return ['preset'=>$preset,'start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d'),'start_local'=>$start,'end_local'=>$end,'start_utc'=>$start->setTimezone($utc)->format('Y-m-d H:i:s'),'end_utc'=>$endExclusive->setTimezone($utc)->format('Y-m-d H:i:s'),'utc_offset'=>$start->format('P'),'type'=>$type,'classification'=>$classification,'error'=>$error]+$ids;
    }

    public function build(array $filter): array
    {
        $repo=new ReportRepository($this->db);$days=(int)$filter['start_local']->diff($filter['end_local'])->days+1;$monthly=$days>120;
        $metrics=$repo->metrics($filter);$trend=$this->fillTrend($repo->trend($filter,$monthly),$filter,$monthly);$technologies=$repo->technologies($filter);$classifications=$repo->classifications($filter);
        $supportStats=[];foreach($repo->supportWorkload($filter) as $row)$supportStats[(int)$row['id']]=$row;$support=[];$toolMinutes=0;foreach($supportStats as $row)$toolMinutes+=(int)$row['scheduled_minutes'];
        foreach($repo->supportDirectory($filter) as $person){$row=$person+($supportStats[(int)$person['id']]??['tool_interviews'=>0,'scheduled_minutes'=>0,'average_minutes'=>0]);$row['workload_percent']=$toolMinutes?round((int)$row['scheduled_minutes']*100/$toolMinutes,1):null;$support[]=$row;}
        $bookingStats=[];foreach($repo->resourceBookings($filter) as $row)$bookingStats[(int)$row['id']]=$row;$resources=[];$weeklyHours=[];foreach($this->db->query('SELECT weekday,is_open,opens_at,closes_at FROM business_hours') as $row)$weeklyHours[(int)$row['weekday']]=$row;
        foreach($repo->resourceDirectory($filter) as $resource){$row=$bookingStats[(int)$resource['id']]??$resource+['booked_interviews'=>0,'booked_minutes'=>0];$available=$this->availableMinutes($filter,$resource,$weeklyHours);$row['available_minutes']=$available;$row['utilization']=$available?round((int)$row['booked_minutes']*100/$available,1):null;$row['over_capacity']=$available>0&&(int)$row['booked_minutes']>$available;$resources[]=$row;}
        $peaks=array_map(static function($r){$r['label']=$r['hour_part'].':'.$r['minute_part'];return $r;},$repo->peakTimes($filter));
        $sources=['candidate'=>0,'admin'=>0];foreach($repo->cancellationSources($filter) as $row)$sources[$row['action']==='booking.cancelled'?'candidate':'admin']=(int)$row['total'];
        $cancelled=(int)$metrics['cancelled_interviews'];$sources['unattributed']=max(0,$cancelled-$sources['candidate']-$sources['admin']);$sources['rate']=(int)$metrics['total_interviews']?round($cancelled*100/(int)$metrics['total_interviews'],1):null;
        return compact('metrics','trend','technologies','classifications','support','resources','peaks','sources','monthly');
    }

    public function exportRows(array $filter): iterable{$statement=(new ReportRepository($this->db))->exportStatement($filter);while($row=$statement->fetch())yield $row;}
    public function query(array $filter): array{return array_filter(['preset'=>$filter['preset'],'start'=>$filter['start'],'end'=>$filter['end'],'type'=>$filter['type'],'technology_id'=>$filter['technology_id'],'support_person_id'=>$filter['support_person_id'],'resource_id'=>$filter['resource_id'],'classification'=>$filter['classification']],static fn($v)=>$v!==''&&$v!==null);}
    public static function csvCell(mixed $value): string{$value=(string)($value??'');return preg_match('/^[\s]*[=+\-@]/u',$value)?"'".$value:$value;}

    private function availableMinutes(array $filter,array $resource,array $weeklyHours): int
    {
        $tz=$this->hours->timezone();$created=(new DateTimeImmutable($resource['created_at'],new DateTimeZone('UTC')))->setTimezone($tz);$day=$filter['start_local'];if($created>$day)$day=$created->setTime(0,0);$total=0;
        while($day<=$filter['end_local']){$hours=$weeklyHours[(int)$day->format('N')]??null;if(!$hours)throw new \RuntimeException('Business hours are not configured for every weekday');if($hours['is_open']){$open=new DateTimeImmutable($day->format('Y-m-d').' '.$hours['opens_at'],$tz);$close=new DateTimeImmutable($day->format('Y-m-d').' '.$hours['closes_at'],$tz);$from=$day->format('Y-m-d')===$created->format('Y-m-d')&&$created>$open?$created:$open;if($from<$close)$total+=(int)floor(($close->getTimestamp()-$from->getTimestamp())/60);} $day=$day->modify('+1 day');}
        return $total;
    }
    private function date(string $value,DateTimeZone $tz):?DateTimeImmutable{$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,$tz);return $date&&$date->format('Y-m-d')===$value?$date:null;}
    private function fillTrend(array $rows,array $filter,bool $monthly):array
    {
        $indexed=[];foreach($rows as $row)$indexed[$row['bucket']]=$row;$filled=[];$cursor=$monthly?$filter['start_local']->modify('first day of this month'):$filter['start_local'];$end=$filter['end_local'];
        while($cursor<=$end){$key=$cursor->format($monthly?'Y-m':'Y-m-d');$filled[]=$indexed[$key]??['bucket'=>$key,'total'=>0,'booked'=>0,'cancelled'=>0];$cursor=$cursor->modify($monthly?'+1 month':'+1 day');}
        return $filled;
    }
}
