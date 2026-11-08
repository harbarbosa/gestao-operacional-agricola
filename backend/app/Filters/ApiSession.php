<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiSession implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! auth('session')->loggedIn()) {
            return service('response')->setStatusCode(401)->setHeader('Cache-Control', 'no-store')
                ->setJSON(['error' => 'É necessário iniciar sessão.']);
        }
        $user = auth('session')->user();
        if (! $user->active || $user->isBanned()) {
            auth('session')->logout();
            return service('response')->setStatusCode(401)->setHeader('Cache-Control', 'no-store')
                ->setJSON(['error' => 'É necessário iniciar sessão.']);
        }
        foreach ($arguments ?? [] as $permission) {
            if (! $user->can($permission)) {
                return service('response')->setStatusCode(403)->setHeader('Cache-Control', 'no-store')
                    ->setJSON(['error' => 'Sem permissão para esta operação.']);
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        $response->setHeader('Cache-Control', 'no-store');
    }
}
