<?php

namespace App\NativeComponents;

use App\Services\AuthService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Login extends NativeComponent
{
    public string $email = '';
    public string $password = '';
    public string $errorMessage = '';
    public string $successMessage = '';

    public function entrar(): void
    {
        $this->errorMessage = '';
        $this->successMessage = '';

        try {
            $authService = app(AuthService::class);

            $authService->login(
                trim($this->email),
                $this->password
            );

            $this->successMessage = 'Login realizado com sucesso! Token armazenado com segurança.';

        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function render(): View
    {
        return view('native.login');
    }
}
