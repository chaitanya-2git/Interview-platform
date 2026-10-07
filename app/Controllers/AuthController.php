<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Support\{View, Session};
use App\Validation\ValidationException;
final class AuthController
{
    public function login(): void { $this->guest(); View::render('auth/login', ['title' => 'Welcome back', 'errors' => [], 'old' => []], 'guest'); }
    public function register(): void { $this->guest(); $this->registrationView(); }
    public function signIn(): void
    {
        $this->guest();
        try { (new AuthService())->login($_POST); View::redirect(''); }
        catch (ValidationException $error) { http_response_code(422); View::render('auth/login', ['title' => 'Welcome back', 'errors' => $error->errors, 'old' => $this->old()], 'guest'); }
    }
    public function signUp(): void
    {
        $this->guest();
        try { (new AuthService())->register($_POST, isset($_FILES['photo']) && is_array($_FILES['photo']) ? $_FILES['photo'] : null, isset($_FILES['resume']) && is_array($_FILES['resume']) ? $_FILES['resume'] : null); Session::flash('Your account is ready. Welcome to BluJay.'); View::redirect('candidate'); }
        catch (ValidationException $error) { http_response_code(422); $this->registrationView($error->errors, $this->old()); }
    }
    private function registrationView(array $errors = [], array $old = []): void { View::render('auth/register', ['title' => 'Create your account', 'errors' => $errors, 'old' => $old, 'technologies' => (new UserRepository())->technologies()], 'guest'); }
    private function old(): array { $old=array_filter(array_intersect_key($_POST,array_flip(['identity','full_name','phone','classification'])),'is_string');if(is_array($_POST['technology_ids']??null))$old['technology_ids']=array_values(array_filter($_POST['technology_ids'],'is_scalar'));return $old; }
    private function guest(): void { if ((new AuthService())->user()) { View::redirect(''); } }
    public function logout(): void { (new AuthService())->logout(); Session::flash('You have signed out securely.'); View::redirect('login'); }
}
