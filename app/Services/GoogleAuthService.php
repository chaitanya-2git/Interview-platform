<?php
declare(strict_types=1);
namespace App\Services;

use App\Support\Database;
use App\Validation\ValidationException;

/**
 * "Continue with Google" for candidates. Firebase Authentication runs the Google popup in the
 * browser; the server verifies the Firebase ID token and maps the Google account to a candidate.
 */
final class GoogleAuthService
{
    private const PENDING_SECONDS = 1800;
    private const MAX_TOKEN_AGE = 600;

    /** @return 'signed_in'|'onboarding' */
    public function signIn(string $idToken): string
    {
        if (!(new RateLimiter())->allow('google-signin-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 30)) { throw new ValidationException(['form' => 'Too many sign-in attempts. Please try again in 15 minutes.']); }
        $google = $this->identity($idToken);
        $query = Database::connection()->prepare('SELECT id, role, active FROM users WHERE google_sub = ? LIMIT 1');
        $query->execute([$google['sub']]);
        $user = $query->fetch();
        if ($user) {
            if (!in_array($user['role'], ['CANDIDATE', 'CONSULTANCY'], true)) { throw new ValidationException(['form' => 'Google sign-in is available for candidate and consultancy accounts only.']); }
            if (!(bool) $user['active']) { throw new ValidationException(['form' => 'Account is inactive. Please contact an administrator.']); }
            (new AuthService())->signInById((int) $user['id']);
            (new AuditService())->record((int) $user['id'], strtolower($user['role']) . '.google_signed_in', 'user', (int) $user['id']);
            return 'signed_in';
        }
        session_regenerate_id(true);
        $_SESSION['google_pending'] = $google + ['expires' => time() + self::PENDING_SECONDS];
        return 'onboarding';
    }

    /** A verified Google identity waiting for profile details or for linking. @return array{sub:string,email:string,name:string}|null */
    public function pending(): ?array
    {
        $pending = $_SESSION['google_pending'] ?? null;
        if (!is_array($pending) || ($pending['expires'] ?? 0) < time()) { unset($_SESSION['google_pending']); return null; }
        return $pending;
    }

    public function forget(): void { unset($_SESSION['google_pending']); }

    public function registerConsultancy(array $data): void
    {
        $pending = $this->pending();
        if ($pending === null) { throw new ValidationException(['form' => 'Your Google sign-in expired. Please continue with Google again.']); }
        $userId = (new ConsultancyService())->register($pending, $data);
        (new AuthService())->signInById($userId);
    }

    public function register(array $data, ?array $photo, ?array $resume): void
    {
        $pending = $this->pending();
        if ($pending === null) { throw new ValidationException(['form' => 'Your Google sign-in expired. Please continue with Google again.']); }
        (new AuthService())->registerWithGoogle($data, $photo, $resume, $pending);
    }

    /** Connects a pending Google identity to a candidate who just proved ownership with their password. */
    public function link(int $userId, array $pending): bool
    {
        $db = Database::connection();
        try {
            $query = $db->prepare("UPDATE users SET google_sub = ? WHERE id = ? AND role = 'CANDIDATE' AND google_sub IS NULL");
            $query->execute([$pending['sub'], $userId]);
            if ($query->rowCount() !== 1) { return false; }
        } catch (\PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) { return false; }
            throw $error;
        }
        try {
            $db->prepare('UPDATE users SET email = ? WHERE id = ? AND email IS NULL')->execute([$pending['email'], $userId]);
        } catch (\PDOException $error) {
            if (($error->errorInfo[1] ?? null) !== 1062) { throw $error; }
        }
        (new AuditService())->record($userId, 'candidate.google_linked', 'user', $userId);
        return true;
    }

    /** @return array{sub:string,email:string,name:string} */
    private function identity(string $idToken): array
    {
        try { $claims = (new FirebaseTokenVerifier())->verify($idToken); }
        catch (\RuntimeException) { throw new ValidationException(['form' => 'Google sign-in could not be confirmed. Please try again.']); }
        $sub = $claims['firebase']['identities']['google.com'][0] ?? null;
        $email = is_string($claims['email'] ?? null) ? mb_strtolower($claims['email']) : '';
        $valid = ($claims['firebase']['sign_in_provider'] ?? null) === 'google.com'
            && is_string($sub) && preg_match('/^[0-9A-Za-z_-]{1,128}$/', $sub) === 1
            && ($claims['email_verified'] ?? false) === true && filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 190
            && ($claims['auth_time'] ?? 0) >= time() - self::MAX_TOKEN_AGE;
        if (!$valid) { throw new ValidationException(['form' => 'Google sign-in could not be confirmed. Please try again.']); }
        $name = is_string($claims['name'] ?? null) ? trim(preg_replace('/[\x00-\x1f]/', '', $claims['name'])) : '';
        return ['sub' => $sub, 'email' => $email, 'name' => mb_substr($name, 0, 120)];
    }
}
