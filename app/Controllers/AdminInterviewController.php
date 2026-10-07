<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\AuthService;
use App\Support\{Database, Scheduling, View};

final class AdminInterviewController
{
    public function index(): void
    {
        $user = (new AuthService())->user(); $db = Database::connection(); $scheduling = new Scheduling();
        $filters = array_map(static fn($value) => is_string($value) ? trim($value) : '', array_intersect_key($_GET, array_flip(['search','date_from','date_to','type','technology_id','support_person_id','resource_id'])));$page=max(1,(int)($_GET['page']??1));
        $result = $scheduling->repository()->adminPaginated($filters, $scheduling->hours()->timezone(),$page);
        View::render('admin/interviews', ['title' => 'Interviews', 'user' => $user, 'filters' => $filters, 'interviews' => $scheduling->presenter()->many($result['rows']),'pagination'=>$result,
            'technologies' => $db->query('SELECT id, name FROM technologies ORDER BY name')->fetchAll(), 'supportPeople' => $db->query('SELECT id, name FROM support_people ORDER BY name')->fetchAll(),'resources'=>$db->query('SELECT id,name FROM resources ORDER BY name')->fetchAll()]);
    }

    public function detail(): void
    {
        $user = (new AuthService())->user(); $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $scheduling = new Scheduling(); $interview = $id ? $scheduling->repository()->adminDetail((int) $id) : null;
        if (!$interview) { http_response_code(404); View::render('error', ['title' => 'Interview not found', 'message' => 'The requested interview does not exist.', 'user' => $user]); return; }
        View::render('admin/interview', ['title' => 'Interview details', 'user' => $user, 'interview' => $scheduling->presenter()->one($interview)]);
    }
}
