<?php
declare(strict_types=1);
namespace App\Services;

use App\Support\NotFoundException;
use App\Validation\ValidationException;
use PDO;

final class CandidateLifecycleService
{
    public function __construct(
        private readonly PDO $db,
        private readonly AuditService $audit,
        private readonly PhotoService $photos = new PhotoService(),
        private readonly ResumeService $resumes = new ResumeService(),
    ) {}

    public function deactivate(int $actorId, int $candidateId): void
    {
        $this->transaction(function () use ($actorId, $candidateId): void {
            $this->lockScheduling();
            $candidate = $this->candidateForUpdate($candidateId);
            if (!(bool) $candidate['active']) {
                throw new ValidationException(['form' => 'Candidate status changed. Refresh and try again.']);
            }
            $query = $this->db->prepare("UPDATE users SET active=0 WHERE id=? AND role='CANDIDATE' AND active=1");
            $query->execute([$candidateId]);
            if ($query->rowCount() !== 1) {
                throw new ValidationException(['form' => 'Candidate status changed. Refresh and try again.']);
            }
            $this->audit->record($actorId, 'candidate.deactivated', 'user', $candidateId, ['candidate_id' => $candidateId, 'candidate_name' => $candidate['full_name']]);
        });
    }

    public function reactivate(int $actorId, int $candidateId): void
    {
        $this->transaction(function () use ($actorId, $candidateId): void {
            $this->lockScheduling();
            $candidate = $this->candidateForUpdate($candidateId);
            if ((bool) $candidate['active']) {
                throw new ValidationException(['form' => 'Candidate status changed. Refresh and try again.']);
            }
            $query = $this->db->prepare("UPDATE users SET active=1 WHERE id=? AND role='CANDIDATE' AND active=0");
            $query->execute([$candidateId]);
            if ($query->rowCount() !== 1) {
                throw new ValidationException(['form' => 'Candidate status changed. Refresh and try again.']);
            }
            $this->audit->record($actorId, 'candidate.reactivated', 'user', $candidateId, ['candidate_id' => $candidateId, 'candidate_name' => $candidate['full_name']]);
        });
    }

    public function delete(int $actorId, int $candidateId, mixed $confirmation): void
    {
        $photo = null;$resume=null;
        $this->transaction(function () use ($actorId, $candidateId, $confirmation, &$photo, &$resume): void {
            $this->lockScheduling();
            $candidate = $this->candidateForUpdate($candidateId);
            $typed = is_string($confirmation) ? trim($confirmation) : '';
            if (!hash_equals('DELETE', $typed)) {
                throw new ValidationException(['confirmation_name' => 'Type DELETE exactly to confirm permanent deletion.']);
            }
            $snapshot = $this->db->prepare('UPDATE interviews SET candidate_reference_id=?,candidate_name_snapshot=?,candidate_classification_snapshot=? WHERE candidate_id=?');
            $snapshot->execute([$candidateId,$candidate['full_name'],$candidate['classification'],$candidateId]);
            $photo = is_string($candidate['photo_filename']) ? $candidate['photo_filename'] : null;
            $resume = is_string($candidate['resume_stored_filename']??null) ? $candidate['resume_stored_filename'] : null;
            $this->audit->record($actorId, 'candidate.deleted', 'user', $candidateId, ['candidate_id' => $candidateId, 'candidate_name' => $candidate['full_name']]);
            $this->db->prepare('DELETE FROM candidate_technologies WHERE candidate_id=?')->execute([$candidateId]);
            $this->db->prepare('DELETE FROM candidate_profiles WHERE user_id=?')->execute([$candidateId]);
            $query = $this->db->prepare("DELETE FROM users WHERE id=? AND role='CANDIDATE'");
            $query->execute([$candidateId]);
            if ($query->rowCount() !== 1) {
                throw new ValidationException(['form' => 'Candidate status changed. Refresh and try again.']);
            }
        });

        if ($photo !== null) {
            $query = $this->db->prepare('SELECT COUNT(*) FROM candidate_profiles WHERE photo_filename=?');
            $query->execute([$photo]);
            if ((int) $query->fetchColumn() === 0 && !$this->photos->remove($photo)) {
                error_log('Candidate photo cleanup failed after deletion for candidate #' . $candidateId);
            }
        }
        if($resume!==null){$query=$this->db->prepare('SELECT COUNT(*) FROM candidate_resumes WHERE stored_filename=?');$query->execute([$resume]);if((int)$query->fetchColumn()===0&&!$this->resumes->remove($resume))error_log('Candidate resume cleanup failed after deletion for candidate #'.$candidateId);}
    }

    private function candidateForUpdate(int $candidateId): array
    {
        if ($candidateId < 1) { throw new NotFoundException('Candidate not found'); }
        $query = $this->db->prepare("SELECT u.id,u.full_name,u.active,p.classification,p.photo_filename,r.stored_filename resume_stored_filename FROM users u JOIN candidate_profiles p ON p.user_id=u.id LEFT JOIN candidate_resumes r ON r.candidate_id=u.id WHERE u.id=? AND u.role='CANDIDATE' FOR UPDATE");
        $query->execute([$candidateId]);
        $candidate = $query->fetch();
        if (!$candidate) { throw new NotFoundException('Candidate not found'); }
        return $candidate;
    }

    private function lockScheduling(): void
    {
        $row = $this->db->query('SELECT id FROM scheduling_mutex WHERE id=1 FOR UPDATE')->fetchColumn();
        if (!$row) { throw new \RuntimeException('Scheduling mutex is not configured'); }
    }

    private function transaction(callable $callback): void
    {
        $this->db->beginTransaction();
        try { $callback(); $this->db->commit(); }
        catch (\Throwable $error) { if ($this->db->inTransaction()) { $this->db->rollBack(); } throw $error; }
    }
}
