<?php

namespace App\Controllers;

class AuthController extends BaseController
{
    public function csrf()
    {
        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON([
            'token' => csrf_hash(),
        ]);
    }

    public function login()
    {
        // Limite partilhado por IP: não depende de cookies fornecidos pelo cliente.
        $key = 'auth-login-' . hash('sha256', $this->request->getIPAddress());
        if (! service('throttler')->check($key, 10, 60)) {
            return $this->response->setStatusCode(429)->setHeader('Retry-After', '60')
                ->setJSON(['error' => 'Demasiadas tentativas. Aguarde um minuto.']);
        }
        $data = $this->request->getJSON(true) ?? [];
        if (! is_array($data) || ! is_string($data['email'] ?? null)
            || ! is_string($data['password'] ?? null)
            || ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)
            || strlen($data['email']) > 254 || strlen($data['password']) > 1024) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Indique email e palavra-passe válidos.']);
        }
        if (auth('session')->loggedIn()) {
            return $this->response->setStatusCode(409)->setJSON(['error' => 'Termine a sessão atual antes de trocar de conta.']);
        }
        $result = auth('session')->attempt([
            'email' => trim($data['email']), 'password' => $data['password'],
        ]);
        if (! $result->isOK()) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Credenciais inválidas ou conta indisponível.']);
        }
        $user = auth('session')->user();
        if (! auth('session')->loggedIn() || ! $user || ! $user->active || $user->isBanned()) {
            auth('session')->logout();
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Credenciais inválidas ou conta indisponível.']);
        }
        session()->regenerate(true);
        return $this->me();
    }

    public function me()
    {
        $user = auth('session')->user();
        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON(['user' => [
            'id' => (int) $user->id,
            'username' => $user->username,
            'groups' => $user->getGroups(),
            'permissions' => array_values(array_filter(
                array_keys(config('AuthGroups')->permissions),
                static fn ($permission) => $user->can($permission),
            )),
        ]]);
    }

    public function logout()
    {
        auth('session')->logout();
        session()->regenerate(true);
        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON(['status' => 'ok']);
    }
}
