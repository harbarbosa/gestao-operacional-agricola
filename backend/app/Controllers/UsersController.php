<?php

namespace App\Controllers;

class UsersController extends BaseController
{
    public function index()
    {
        $users = auth()->getProvider()->orderBy('id')->paginate(25);
        return $this->response->setJSON([
            'users' => array_map(static fn ($user) => [
                'id' => (int) $user->id,
                'username' => $user->username,
                'active' => (bool) $user->active,
                'groups' => $user->getGroups(),
            ], $users),
            'pageSize' => 25,
        ]);
    }
}
