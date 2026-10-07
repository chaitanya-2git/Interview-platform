<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\{AuditService,AuthService,CandidateLifecycleService};
use App\Support\{Database,NotFoundException,Session,View};
use App\Validation\ValidationException;

final class AdminCandidateLifecycleController
{
    public function deactivate(): void { $this->change('deactivate'); }
    public function reactivate(): void { $this->change('reactivate'); }

    public function delete(): void
    {
        $id=$this->candidateId();$user=(new AuthService())->user();
        if(!$id){Session::flash('Candidate not found.');View::redirect('admin/candidates');}
        try{
            $this->service()->delete((int)$user['id'],$id,$_POST['confirmation_name']??null);
            Session::flash('Candidate permanently deleted.');View::redirect('admin/candidates');
        }catch(NotFoundException){Session::flash('Candidate not found.');View::redirect('admin/candidates');}
        catch(ValidationException $error){Session::flash((string)(array_values($error->errors)[0]??'The candidate could not be deleted.'));View::redirect('admin/candidate?id='.$id);}
    }

    private function change(string $action): void
    {
        $id=$this->candidateId();$user=(new AuthService())->user();
        if(!$id){Session::flash('Candidate not found.');View::redirect('admin/candidates');}
        try{
            $service=$this->service();$service->{$action}((int)$user['id'],$id);
            Session::flash($action==='deactivate'?'Candidate deactivated. Interview history and profile data were preserved.':'Candidate reactivated.');
            View::redirect('admin/candidate?id='.$id);
        }catch(NotFoundException){Session::flash('Candidate not found.');View::redirect('admin/candidates');}
        catch(ValidationException $error){Session::flash((string)(array_values($error->errors)[0]??'The candidate status could not be changed.'));View::redirect('admin/candidate?id='.$id);}
    }

    private function candidateId(): ?int
    {
        $id=filter_var($_POST['candidate_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return $id?(int)$id:null;
    }

    private function service(): CandidateLifecycleService
    {
        $db=Database::connection();return new CandidateLifecycleService($db,new AuditService($db));
    }
}
