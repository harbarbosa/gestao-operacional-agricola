<?php

namespace Config;

class AuthGroups extends \CodeIgniter\Shield\Config\AuthGroups
{
    public string $defaultGroup = 'worker';
    public array $groups = [
        'admin' => ['title' => 'Administrador', 'description' => 'Administração da empresa.'],
        'supervisor' => ['title' => 'Chefe de equipa', 'description' => 'Operação das equipas autorizadas.'],
        'worker' => ['title' => 'Trabalhador', 'description' => 'Consulta dos próprios dados.'],
    ];
    public array $permissions = [
        'users.view' => 'Consultar utilizadores.',
        'field.record' => 'Registar trabalho nas equipas autorizadas.',
        'own.view' => 'Consultar os próprios dados.',
    ];
    public array $matrix = [
        'admin' => ['users.view', 'field.record', 'own.view'],
        'supervisor' => ['field.record', 'own.view'],
        'worker' => ['own.view'],
    ];
}
