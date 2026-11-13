<?php
declare(strict_types=1);
namespace App\Services;

use App\Support\Database;
use App\Validation\{Input, ValidationException};

/**
 * Candidate password reset: Firebase proves the person controls the registered phone
 * number (SMS OTP), then the server grants a short-lived, session-bound reset window.
 */
final class PasswordResetService
{
    private const GRANT_SECONDS = 600;
    private const MAX_TOKEN_AGE = 600;

    /** Checks a phone number before Firebase sends an SMS, so no OTP is spent on unknown numbers. */
    public function lookup(string $rawPhone): string
    {
        $limiter = new RateLimiter();
        if (!$limiter->allow('reset-lookup-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 20)) { throw new ValidationException(['phone' => 'Too many attempts. Please try again in 15 minutes.']); }
        $phone = Input::phone($rawPhone);
        if ($phone === null) { throw new ValidationException(['phone' => 'Enter a 10-digit Indian mobile number or an international number with +country code.']); }
        if (!$limiter->allow('reset-lookup-phone', $phone, 5, 3600)) { throw new ValidationException(['phone' => 'Too many codes requested for this number. Please try again in one hour.']); }
        $this->candidateByPhone($phone);
        return $phone;
    }

    public function verify(string $idToken): void
    {
        if (!(new RateLimiter())->allow('reset-verify-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 20)) { throw new ValidationException(['form' => 'Too many attempts. Please try again in 15 minutes.']); }
        try { $claims = (new FirebaseTokenVerifier())->verify($idToken); }
        catch (\RuntimeException) { throw new ValidationException(['form' => 'We could not confirm the verification code. Please request a new code.']); }
        $phone = is_string($claims['phone_number'] ?? null) ? Input::phone($claims['phone_number']) : null;
        $fresh = ($claims['auth_time'] ?? 0) >= time() - self::MAX_TOKEN_AGE;
        if ($phone === null || ($claims['firebase']['sign_in_provider'] ?? null) !== 'phone' || !$fresh) {
            throw new ValidationException(['form' => 'We could not confirm the verification code. Please request a new code.']);
        }
        $user = $this->candidateByPhone($phone, 'form');
        session_regenerate_id(true);
        $_SESSION['password_reset'] = ['user_id' => (int) $user['id'], 'phone' => $phone, 'expires' => time() + self::GRANT_SECONDS];
        (new AuditService())->record((int) $user['id'], 'user.password_reset_verified', 'user', (int) $user['id']);
    }

    /** @return array{user_id:int,phone:string,expires:int}|null */
    public function grant(): ?array
    {
        $grant = $_SESSION['password_reset'] ?? null;
        if (!is_array($grant) || ($grant['expires'] ?? 0) < time()) { unset($_SESSION['password_reset']); return null; }
        return $grant;
    }

    public function reset(array $data): void
    {
        $grant = $this->grant();
        if ($grant === null) { throw new ValidationException(['form' => 'Your verification expired. Please verify your phone number again.']); }
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';
        $confirmation = is_string($data['password_confirmation'] ?? null) ? $data['password_confirmation'] : '';
        $errors = [];
        if (!Input::password($password)) { $errors['password'] = 'Use at least 12 characters, up to 72 bytes.'; }
        elseif (!hash_equals($password, $confirmation)) { $errors['password_confirmation'] = 'The passwords do not match.'; }
        if ($errors) { throw new ValidationException($errors); }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $query = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND phone = ? AND role = 'CANDIDATE' AND active = 1");
            $query->execute([password_hash($password, PASSWORD_DEFAULT), $grant['user_id'], $grant['phone']]);
            if ($query->rowCount() !== 1) { throw new ValidationException(['form' => 'This account can no longer be reset. Please contact an administrator.']); }
            (new AuditService())->record($grant['user_id'], 'user.password_reset', 'user', $grant['user_id']);
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) { $db->rollBack(); }
            throw $error;
        }
        unset($_SESSION['password_reset']);
        session_regenerate_id(true);
    }

    private function candidateByPhone(string $phone, string $field = 'phone'): array
    {
        $query = Database::connection()->prepare("SELECT u.id, u.active, p.consultancy_id FROM users u LEFT JOIN candidate_profiles p ON p.user_id = u.id WHERE u.phone = ? AND u.role = 'CANDIDATE' LIMIT 1");
        $query->execute([$phone]);
        $user = $query->fetch();
        if (!$user) { throw new ValidationException([$field => 'No candidate account uses this phone number.']); }
        if ($user['consultancy_id']) { throw new ValidationException([$field => 'This profile is managed by your consultancy. Please contact them.']); }
        if (!(bool) $user['active']) { throw new ValidationException([$field => 'Account is inactive. Please contact an administrator.']); }
        return $user;
    }
}
