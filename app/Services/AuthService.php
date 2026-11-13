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
        if ($user['role'] === 'CANDIDATE' && $this->managedByConsultancy((int) $user['id'])) {
            throw new ValidationException(['form' => 'This profile is managed by your consultancy. Please contact them for interview bookings.']);
        }
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
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';
        $errors = Input::password($password) ? [] : ['password' => 'Use at least 12 characters, up to 72 bytes.'];
        $id = $this->createCandidate($data, $photo, $resume, password_hash($password, PASSWORD_DEFAULT), $errors);
        $this->authenticate($id);
    }
    /** Google sign-up: no password is chosen, so the stored hash is of random bytes nobody knows. */
    public function registerWithGoogle(array $data, ?array $photo, ?array $resume, array $google): void
    {
        if (!(new RateLimiter())->allow('registration-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 10, 3600)) { throw new ValidationException(['form' => 'Too many registration attempts. Please try again in one hour.']); }
        $id = $this->createCandidate($data, $photo, $resume, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), [], $google);
        $this->authenticate($id);
    }
    public function signInById(int $id): void { $this->authenticate($id); }
    /** A student added by a partner consultancy: no login of their own; resume required, photo optional. */
    public function createManagedCandidate(array $data, ?array $photo, ?array $resume, int $consultancyId): int
    {
        return $this->createCandidate($data, $photo, $resume, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), [], null, ['consultancy_id' => $consultancyId, 'photo_required' => false]);
    }
    private function createCandidate(array $data, ?array $photo, ?array $resume, string $passwordHash, array $errors = [], ?array $google = null, array $options = []): int
    {
        $name = Input::text($data, 'full_name');
        $phone = Input::phone(Input::text($data, 'phone'));
        $classification = Input::text($data, 'classification');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || preg_match('/[\x00-\x1f]/', $name)) { $errors['full_name'] = 'Enter your full name (2–120 characters).'; }
        if ($phone === null) { $errors['phone'] = 'Enter a 10-digit Indian mobile number or an international number with +country code.'; }
        if (!in_array($classification, ['Genuine', 'Fake'], true)) { $errors['classification'] = 'Select Genuine or Fake.'; }
        $missing = static fn(?array $file): bool => $file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;
        if (($options['photo_required'] ?? true) && $missing($photo)) { $errors['photo'] = 'Upload a profile photo (JPG or PNG, up to 2 MB).'; }
        if ($missing($resume)) { $errors['resume'] = 'Upload your resume (PDF or DOCX, up to 5 MB).'; }
        $experience = null;
        try { $experience = CandidateTechStackService::totalYears($data['experience_years'] ?? null); } catch (ValidationException $error) { $errors += $error->errors; }
        if ($errors) { throw new ValidationException($errors); }
        $db = Database::connection();$photos=new PhotoService();$resumes=new ResumeService($db,new AuditService($db));$stored=null;$storedResume=null;
        try {
            $stored=$photos->store($photo);$storedResume=$resumes->storeUploaded($resume);
            $db->beginTransaction();
            $stackService=new CandidateTechStackService($db);$technologyIds=$stackService->validate($data['technology_ids']??null,[],true,true);$technologyYears=$stackService->years($data['technology_years']??null,$technologyIds);
            $query = $db->prepare("INSERT INTO users (role, full_name, phone, email, google_sub, password_hash) VALUES ('CANDIDATE', ?, ?, ?, ?, ?)");
            $query->execute([$name, $phone, $google['email'] ?? null, $google['sub'] ?? null, $passwordHash]);
            $id = (int) $db->lastInsertId();
            $query = $db->prepare('INSERT INTO candidate_profiles (user_id, consultancy_id, technology_id, classification, experience_years, photo_filename) VALUES (?, ?, ?, ?, ?, ?)');
            $query->execute([$id, $options['consultancy_id'] ?? null, null, $classification, $experience, $stored]);$stackService->replace($id,$technologyIds,$technologyYears);
            if($storedResume){$query=$db->prepare('INSERT INTO candidate_resumes(candidate_id,stored_filename,original_filename,mime_type,file_size) VALUES(?,?,?,?,?)');$query->execute([$id,$storedResume['stored_filename'],$storedResume['original_filename'],$storedResume['mime_type'],$storedResume['file_size']]);}
            (new AuditService())->record($id, isset($options['consultancy_id']) ? 'consultancy.student.added' : 'candidate.registered', 'user', $id, $google ? ['method' => 'google'] : (isset($options['consultancy_id']) ? ['consultancy_id' => $options['consultancy_id']] : []));
            if($storedResume)(new AuditService())->record($id,'candidate.resume_uploaded','user',$id,['mime_type'=>$storedResume['mime_type'],'file_size'=>$storedResume['file_size']]);
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $photos->remove($stored);if($storedResume)$resumes->remove($storedResume['stored_filename']);
            if ($error instanceof \PDOException && ($error->errorInfo[1] ?? null) === 1062) {
                $detail = (string) ($error->errorInfo[2] ?? '');
                if (str_contains($detail, 'google_sub')) { throw new ValidationException(['form' => 'This Google account is already connected to a BluJay account. Please sign in with Google.']); }
                if (str_contains($detail, 'email')) { throw new ValidationException(['form' => 'This email address is already used by another BluJay account.']); }
                throw new ValidationException(isset($options['consultancy_id']) ? ['phone' => 'This phone number is already registered with BluJay, so it cannot be added as your student.'] : ['phone' => 'An account already uses this phone number. Please sign in.', 'phone_taken' => '1']);
            }
            throw $error;
        }
        return $id;
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
    private function managedByConsultancy(int $userId): bool
    {
        $query = Database::connection()->prepare('SELECT consultancy_id FROM candidate_profiles WHERE user_id = ?');
        $query->execute([$userId]);
        return (bool) $query->fetchColumn();
    }
}
