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
        'catalog.manage' => 'Consultar e gerir cadastros operacionais.',
        'prices.manage' => 'Configurar e consultar tarifas.',
        'field.record' => 'Registar trabalho nas equipas autorizadas.',
        'own.view' => 'Consultar os próprios dados.',
    ];
    public array $matrix = [
        'admin' => ['prices.manage', 'catalog.manage', 'users.view', 'field.record', 'own.view'],
        'supervisor' => ['field.record', 'own.view'],
        'worker' => ['own.view'],
    ];
}
