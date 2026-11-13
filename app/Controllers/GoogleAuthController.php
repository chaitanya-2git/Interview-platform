<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Services\{AuthService, GoogleAuthService};
use App\Support\{Firebase, Session, View};
use App\Validation\{Input, ValidationException};

final class GoogleAuthController
{
    /** Receives the Firebase ID token from the Google popup. */
    public function callback(): void
    {
        $this->guest();
        try {
            $next = (new GoogleAuthService())->signIn(Input::text($_POST, 'id_token'));
            if ($next === 'signed_in') { View::redirect(''); }
            View::redirect('register/google');
        } catch (ValidationException $error) {
            http_response_code(422);
            (new AuthController())->renderLogin($error->errors);
        }
    }

    public function form(): void
    {
        $this->guest();
        $google = (new GoogleAuthService())->pending();
        if ($google === null) { Session::flash('Please continue with Google again.'); View::redirect('login'); }
        if (($_GET['as'] ?? '') !== 'candidate') {
            View::render('auth/google-choose', ['title' => 'How will you use BluJay?', 'google' => $google], 'guest');
            return;
        }
        $this->render($google, [], ['full_name' => $google['name']]);
    }

    public function consultancyForm(): void
    {
        $this->guest();
        $google = (new GoogleAuthService())->pending();
        if ($google === null) { Session::flash('Please continue with Google again.'); View::redirect('login'); }
        View::render('auth/consultancy-register', ['title' => 'Register your consultancy', 'google' => $google, 'errors' => [], 'old' => ['contact_name' => $google['name']]], 'guest');
    }

    public function consultancyRegister(): void
    {
        $this->guest();
        $service = new GoogleAuthService();
        try {
            $service->registerConsultancy($_POST);
            Session::flash('Thanks! Your consultancy registration was submitted for approval.');
            View::redirect('consultancy');
        } catch (ValidationException $error) {
            $google = $service->pending();
            if ($google === null) { Session::flash($error->errors['form'] ?? 'Please continue with Google again.'); View::redirect('login'); }
            http_response_code(422);
            $old = array_filter(array_intersect_key($_POST, array_flip(['name', 'contact_name', 'phone', 'city', 'website'])), 'is_string');
            View::render('auth/consultancy-register', ['title' => 'Register your consultancy', 'google' => $google, 'errors' => $error->errors, 'old' => $old], 'guest');
        }
    }

    public function register(): void
    {
        $this->guest();
        $service = new GoogleAuthService();
        try {
            $service->register($_POST, $this->file('photo'), $this->file('resume'));
            Session::flash('Your account is ready. Welcome to BluJay.');
            View::redirect('candidate');
        } catch (ValidationException $error) {
            $google = $service->pending();
            if ($google === null) { Session::flash($error->errors['form'] ?? 'Please continue with Google again.'); View::redirect('login'); }
            http_response_code(422);
            $this->render($google, $error->errors, $this->old());
        }
    }

    public function cancel(): void
    {
        (new GoogleAuthService())->forget();
        View::redirect('login');
    }

    private function render(array $google, array $errors, array $old): void
    {
        View::render('auth/google-register', ['title' => 'Complete your profile', 'google' => $google, 'errors' => $errors, 'old' => $old, 'technologies' => (new UserRepository())->technologies()], 'guest');
    }

    private function file(string $key): ?array { return isset($_FILES[$key]) && is_array($_FILES[$key]) ? $_FILES[$key] : null; }

    private function old(): array
    {
        $old = array_filter(array_intersect_key($_POST, array_flip(['full_name', 'phone', 'classification', 'experience_years'])), 'is_string');
        if (is_array($_POST['technology_ids'] ?? null)) { $old['technology_ids'] = array_values(array_filter($_POST['technology_ids'], 'is_scalar')); }
        if (is_array($_POST['technology_years'] ?? null)) { $old['technology_years'] = array_filter($_POST['technology_years'], 'is_scalar'); }
        return $old;
    }

    private function guest(): void { if ((new AuthService())->user()) { View::redirect(''); } }
}
