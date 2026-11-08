<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiNoStore implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null) {}
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        $response->setHeader('Cache-Control', 'no-store');
    }
}
