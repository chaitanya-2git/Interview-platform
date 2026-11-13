<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\{AuthService, PasswordResetService};
use App\Support\{Firebase, Session, View};
use App\Validation\{Input, ValidationException};

final class PasswordResetController
{
    public function form(): void
    {
        $this->guest();
        Firebase::sendContentSecurityPolicy();
        View::render('auth/forgot-password', ['title' => 'Reset your password', 'errors' => [], 'configured' => Firebase::configured(), 'firebase' => Firebase::config()], 'guest');
    }

    /** JSON: confirms a registered candidate number before the browser asks Firebase to send an SMS. */
    public function lookup(): void
    {
        $this->guest();
        header('Content-Type: application/json');
        try {
            $phone = (new PasswordResetService())->lookup(Input::text($_POST, 'phone'));
            echo json_encode(['ok' => true, 'phone' => $phone]);
        } catch (ValidationException $error) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $error->errors['phone'] ?? 'Please check the phone number.']);
        }
    }

    public function verify(): void
    {
        $this->guest();
        try {
            (new PasswordResetService())->verify(Input::text($_POST, 'id_token'));
            View::redirect('reset-password');
        } catch (ValidationException $error) {
            http_response_code(422);
            Firebase::sendContentSecurityPolicy();
            View::render('auth/forgot-password', ['title' => 'Reset your password', 'errors' => $error->errors, 'configured' => Firebase::configured(), 'firebase' => Firebase::config()], 'guest');
        }
    }

    public function resetForm(): void
    {
        $this->guest();
        $grant = (new PasswordResetService())->grant();
        if ($grant === null) { Session::flash('Verify your phone number to reset your password.'); View::redirect('forgot-password'); }
        View::render('auth/reset-password', ['title' => 'Choose a new password', 'errors' => [], 'phone' => $grant['phone']], 'guest');
    }

    public function reset(): void
    {
        $this->guest();
        $service = new PasswordResetService();
        try {
            $service->reset($_POST);
            Session::flash('Your password has been changed. Sign in with your new password.');
            View::redirect('login');
        } catch (ValidationException $error) {
            $grant = $service->grant();
            if ($grant === null) { Session::flash($error->errors['form'] ?? 'Your verification expired. Please verify again.'); View::redirect('forgot-password'); }
            http_response_code(422);
            View::render('auth/reset-password', ['title' => 'Choose a new password', 'errors' => $error->errors, 'phone' => $grant['phone']], 'guest');
        }
    }

    private function guest(): void { if ((new AuthService())->user()) { View::redirect(''); } }
}
