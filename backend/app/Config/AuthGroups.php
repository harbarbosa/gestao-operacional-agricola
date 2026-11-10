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
        'finance.manage' => 'Fechar produção por período e registar pagamentos.',
        'performance.view' => 'Consultar avaliações e desempenho dos trabalhadores.',
        'reports.view' => 'Consultar e exportar relatórios de todos os trabalhos e trabalhadores.',
        'users.manage' => 'Criar e gerir contas, perfis e permissões.',
        'users.view' => 'Consultar utilizadores.',
        'catalog.manage' => 'Consultar e gerir cadastros operacionais.',
        'prices.manage' => 'Configurar e consultar tarifas.',
        'field.manage' => 'Gerir e fechar todas as atividades de campo.',
        'field.record' => 'Registar trabalho nas equipas autorizadas.',
        'own.view' => 'Consultar os próprios dados.',
    ];
    public array $matrix = [
        'admin' => ['finance.manage', 'performance.view', 'reports.view', 'users.manage', 'field.manage', 'prices.manage', 'catalog.manage', 'users.view', 'field.record', 'own.view'],
        'supervisor' => ['performance.view', 'field.record', 'own.view'],
        'worker' => ['own.view'],
    ];
}
