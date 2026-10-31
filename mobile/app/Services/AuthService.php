<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Native\Mobile\Facades\SecureStorage;
use Native\Mobile\SecureStorageAccessibility;
use RuntimeException;

class AuthService
{
    private const TOKEN_KEY = 'ponto_facil_token';

    private const API_URL = 'http://fmel-ponto-facil.duckdns.org/api';

    /**
     * Realiza login na API.
     */
    public function login(
        string $email,
        string $password
    ): array {
        $response = Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->post(self::API_URL . '/login', [
                'email' => $email,
                'password' => $password,
                'device_name' => 'ponto-facil-mobile',
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                $response->json('message', 'Não foi possível realizar o login.')
            );
        }

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException(
                'A API não retornou um token de autenticação válido.'
            );
        }

        $stored = SecureStorage::set(
            self::TOKEN_KEY,
            $token,
            SecureStorageAccessibility::WhenUnlocked
        );

        if (! $stored) {
            throw new RuntimeException(
                'Não foi possível armazenar o token com segurança.'
            );
        }

        return [
            'user' => $response->json('user'),
            'token' => $token,
        ];
    }

    /**
     * Realiza cadastro na API.
     */
    public function register(
        string $name,
        string $email,
        string $password,
        string $passwordConfirmation
    ): array {
        $response = Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->post(self::API_URL . '/register', [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
                'device_name' => 'ponto-facil-mobile',
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                $response->json('message', 'Não foi possível realizar o cadastro.')
            );
        }

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException(
                'A API não retornou um token de autenticação válido.'
            );
        }

        $stored = SecureStorage::set(
            self::TOKEN_KEY,
            $token,
            SecureStorageAccessibility::WhenUnlocked
        );

        if (! $stored) {
            throw new RuntimeException(
                'Não foi possível armazenar o token com segurança.'
            );
        }

        return [
            'user' => $response->json('user'),
            'token' => $token,
        ];
    }

    /**
     * Recupera o token armazenado.
     */
    public function getToken(): ?string
    {
        return SecureStorage::get(self::TOKEN_KEY);
    }

    /**
     * Consulta o usuário autenticado.
     */
    public function user(): array
    {
        $token = $this->getToken();

        if (! $token) {
            throw new RuntimeException('Usuário não autenticado.');
        }

        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout(20)
            ->get(self::API_URL . '/user');

        if ($response->failed()) {
            throw new RuntimeException(
                $response->json('message', 'Não foi possível consultar o usuário.')
            );
        }

        return $response->json();
    }

    /**
     * Encerra a sessão.
     */
    public function logout(): void
    {
        $token = $this->getToken();

        if ($token) {
            Http::acceptJson()
                ->withToken($token)
                ->timeout(20)
                ->post(self::API_URL . '/logout');
        }

        SecureStorage::delete(self::TOKEN_KEY);
    }

    /**
     * Verifica se existe token armazenado.
     */
    public function isAuthenticated(): bool
    {
        return ! empty($this->getToken());
    }
}