<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Services\AuthService;
use App\Support\View;
final class Authorize
{
    public static function check(string $role): bool
    {
        $user = (new AuthService())->user();
        if (!$user) { View::redirect('login'); }
        if ($role !== 'AUTH' && $user['role'] !== $role) {
            http_response_code(403);
            View::render('error', ['title' => 'Access restricted', 'message' => 'Your account does not have access to this area.'], 'guest');
            return false;
        }
        return true;
    }
}
