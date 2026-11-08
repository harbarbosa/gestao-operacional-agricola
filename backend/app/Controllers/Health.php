<?php

namespace App\Controllers;

class Health extends BaseController
{
    public function index()
    {
        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON(['status' => 'ok']);
    }
}
