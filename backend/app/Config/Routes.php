<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->get('api/v1/health', 'Health::index');
$routes->get('api/v1/auth/csrf', 'AuthController::csrf');
$routes->post('api/v1/auth/login', 'AuthController::login');
$routes->get('api/v1/auth/me', 'AuthController::me', ['filter' => 'api-session']);
$routes->post('api/v1/auth/logout', 'AuthController::logout', ['filter' => 'api-session']);
$routes->get('api/v1/users', 'UsersController::index', ['filter' => 'api-session:users.view']);

// Allowlist de recursos: nenhuma rota aceita nomes arbitrários de tabelas.
foreach (['workers', 'teams', 'clients', 'units', 'services'] as $resource) {
    $options = ['filter' => 'api-session:catalog.manage'];
    $routes->get('api/v1/catalog/' . $resource, 'CatalogController::index/' . $resource, $options);
    $routes->get('api/v1/catalog/' . $resource . '/([1-9][0-9]*)', 'CatalogController::show/' . $resource . '/$1', $options);
    $routes->post('api/v1/catalog/' . $resource, 'CatalogController::create/' . $resource, $options);
    $routes->put('api/v1/catalog/' . $resource . '/([1-9][0-9]*)', 'CatalogController::update/' . $resource . '/$1', $options);
}
