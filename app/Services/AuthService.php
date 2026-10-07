<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\UserRepository;
use App\Support\{Database, Session};
use App\Validation\{Input, ValidationException};
final class AuthService
{
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    public function user(): ?array { return isset($_SESSION['user_id']) ? (new UserRepository())->byId((int) $_SESSION['user_id']) : null; }
    public function login(array $data): void
    {
        $identity = Input::text($data, 'identity');
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';
        $limiter = new RateLimiter();
        $ipAllowed = $limiter->allow('login-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 50);
        $identityAllowed = $limiter->allow('login-identity', mb_strtolower(Input::phone($identity) ?? $identity), 10);
        if (!$ipAllowed || !$identityAllowed) { throw new ValidationException(['form' => 'Too many sign-in attempts. Please try again in 15 minutes.']); }
        $user = mb_strlen($identity) <= 190 ? (new UserRepository())->byIdentity($identity) : null;
        $valid = strlen($password) <= 72 && !str_contains($password, "\0") && password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);
        if (!$valid || !$user) { throw new ValidationException(['form' => 'The sign-in details are incorrect. Please try again.']); }
        if (!(bool) $user['active']) {
            $message = $user['role'] === 'CANDIDATE'
                ? 'Account is inactive. Please contact an administrator.'
                : 'The sign-in details are incorrect. Please try again.';
            throw new ValidationException(['form' => $message]);
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $query = Database::connection()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $query->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        $this->authenticate((int) $user['id']);
    }
    public function register(array $data, ?array $photo, ?array $resume=null): void
    {
        if (!(new RateLimiter())->allow('registration-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 10, 3600)) { throw new ValidationException(['form' => 'Too many registration attempts. Please try again in one hour.']); }
        $name = Input::text($data, 'full_name');
        $phone = Input::phone(Input::text($data, 'phone'));
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';
        $classification = Input::text($data, 'classification');
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || preg_match('/[\x00-\x1f]/', $name)) { $errors['full_name'] = 'Enter your full name (2–120 characters).'; }
        if ($phone === null) { $errors['phone'] = 'Enter a 10-digit Indian mobile number or an international number with +country code.'; }
        if (!Input::password($password)) { $errors['password'] = 'Use at least 12 characters, up to 72 bytes.'; }
        if (!in_array($classification, ['Genuine', 'Fake'], true)) { $errors['classification'] = 'Select Genuine or Fake.'; }
        if ($errors) { throw new ValidationException($errors); }
        $db = Database::connection();$photos=new PhotoService();$resumes=new ResumeService($db,new AuditService($db));$stored=null;$storedResume=null;
        try {
            $stored=$photos->store($photo);$storedResume=$resumes->storeUploaded($resume);
            $db->beginTransaction();
            $stackService=new CandidateTechStackService($db);$technologyIds=$stackService->validate($data['technology_ids']??null,[],true,true);
            $query = $db->prepare("INSERT INTO users (role, full_name, phone, password_hash) VALUES ('CANDIDATE', ?, ?, ?)");
            $query->execute([$name, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) $db->lastInsertId();
            $query = $db->prepare('INSERT INTO candidate_profiles (user_id, technology_id, classification, photo_filename) VALUES (?, ?, ?, ?)');
            $query->execute([$id, null, $classification, $stored]);$stackService->replace($id,$technologyIds);
            if($storedResume){$query=$db->prepare('INSERT INTO candidate_resumes(candidate_id,stored_filename,original_filename,mime_type,file_size) VALUES(?,?,?,?,?)');$query->execute([$id,$storedResume['stored_filename'],$storedResume['original_filename'],$storedResume['mime_type'],$storedResume['file_size']]);}
            (new AuditService())->record($id, 'candidate.registered', 'user', $id);
            if($storedResume)(new AuditService())->record($id,'candidate.resume_uploaded','user',$id,['mime_type'=>$storedResume['mime_type'],'file_size'=>$storedResume['file_size']]);
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $photos->remove($stored);if($storedResume)$resumes->remove($storedResume['stored_filename']);
            if ($error instanceof \PDOException && ($error->errorInfo[1] ?? null) === 1062) { throw new ValidationException(['phone' => 'An account already uses this phone number. Please sign in.']); }
            throw $error;
        }
        $this->authenticate($id);
    }
    private function authenticate(int $id): void
    {
        Session::clear();
        $_SESSION['user_id'] = $id;
        $_SESSION['authenticated_at'] = time();
        $_SESSION['last_activity'] = time();
        Session::token();
    }
    public function logout(): void { Session::clear(); }
}
