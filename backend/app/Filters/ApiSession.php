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
        $path=ltrim($request->getUri()->getPath(),'/');
        if(!config('Agroperi')->legacyEnabled&&preg_match('#^api/v1/(?:contracts|harvests|clients|sites|services|units|prices|catalog/(?:clients|sites|services|units|teams)|admin/portfolio|admin/finance|admin/settlements)(?:/|$)#',$path))return service('response')->setStatusCode(403)->setJSON(['error'=>'Módulo desativado no modo simplificado.']);
        $user = auth('session')->user();
        if (! $user->active || $user->isBanned()) {
            auth('session')->logout();
            return service('response')->setStatusCode(401)->setHeader('Cache-Control', 'no-store')
                ->setJSON(['error' => 'É necessário iniciar sessão.']);
        }
        $state = db_connect()->table('agri_user_versions')->where('user_id', $user->id)->get()->getRowArray();
        $version = (int) ($state['security_version'] ?? 1);
        $sessionVersion = session()->get('agri_user_security_version');
        if (($sessionVersion === null && $version > 1) || ($sessionVersion !== null && (int) $sessionVersion !== $version)) {
            auth('session')->logout();
            return service('response')->setStatusCode(401)->setHeader('Cache-Control', 'no-store')->setJSON(['error' => 'Os dados de acesso foram alterados. Inicie sessão novamente.']);
        }
        session()->set('agri_user_security_version', $version);
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
