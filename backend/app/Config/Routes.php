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
foreach (['workers', 'teams', 'clients', 'units', 'services', 'sites'] as $resource) {
    $options = ['filter' => 'api-session:catalog.manage'];
    $routes->get('api/v1/catalog/' . $resource, 'CatalogController::index/' . $resource, $options);
    $routes->get('api/v1/catalog/' . $resource . '/([1-9][0-9]*)', 'CatalogController::show/' . $resource . '/$1', $options);
    $routes->post('api/v1/catalog/' . $resource, 'CatalogController::create/' . $resource, $options);
    $routes->put('api/v1/catalog/' . $resource . '/([1-9][0-9]*)', 'CatalogController::update/' . $resource . '/$1', $options);
}

$prices = ['filter' => 'api-session:prices.manage'];
$routes->get('api/v1/prices', 'PricesController::index', $prices);
$routes->post('api/v1/prices', 'PricesController::create', $prices);
$routes->post('api/v1/prices/preview', 'PricesController::preview', $prices);
$routes->post('api/v1/prices/([1-9][0-9]*)/end', 'PricesController::end/$1', $prices);
$routes->post('api/v1/prices/([1-9][0-9]*)/cancel', 'PricesController::cancel/$1', $prices);

$field = ['filter' => 'api-session:field.manage'];
$routes->get('api/v1/work-logs', 'WorkLogsController::index', $field);
$routes->get('api/v1/work-logs/([1-9][0-9]*)', 'WorkLogsController::show/$1', $field);
$routes->post('api/v1/work-logs', 'WorkLogsController::create', $field);
$routes->put('api/v1/work-logs/([1-9][0-9]*)', 'WorkLogsController::update/$1', $field);
$routes->post('api/v1/work-logs/([1-9][0-9]*)/cancel', 'WorkLogsController::cancel/$1', $field);
