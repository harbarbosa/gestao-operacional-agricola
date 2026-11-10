<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->get('api/v1/health', 'Health::index');
$routes->get('api/v1/auth/csrf', 'AuthController::csrf');
$routes->post('api/v1/auth/login', 'AuthController::login');
$routes->get('api/v1/auth/me', 'AuthController::me', ['filter' => 'api-session']);
$routes->post('api/v1/auth/logout', 'AuthController::logout', ['filter' => 'api-session']);
$routes->get('api/v1/users/metadata', 'UsersController::metadata', ['filter' => 'api-session:users.view']);
$routes->get('api/v1/users/([1-9][0-9]*)', 'UsersController::show/$1', ['filter' => 'api-session:users.view']);
$routes->post('api/v1/users', 'UsersController::create', ['filter' => 'api-session:users.manage']);
$routes->put('api/v1/users/([1-9][0-9]*)', 'UsersController::update/$1', ['filter' => 'api-session:users.manage']);
$routes->delete('api/v1/users/([1-9][0-9]*)', 'UsersController::deactivate/$1', ['filter' => 'api-session:users.manage']);
$routes->get('api/v1/users', 'UsersController::index', ['filter' => 'api-session:users.view']);

// Allowlist de recursos: nenhuma rota aceita nomes arbitrários de tabelas.
foreach (['workers', 'teams', 'clients', 'units', 'services', 'sites'] as $resource) {
    $options = ['filter' => 'api-session:catalog.manage'];
    $routes->get('api/v1/catalog/' . $resource, 'CatalogController::index/' . $resource, $options);
    $routes->get('api/v1/catalog/' . $resource . '/([1-9][0-9]*)', 'CatalogController::show/' . $resource . '/$1', $options);
    $routes->post('api/v1/catalog/' . $resource, 'CatalogController::create/' . $resource, $options);
    $routes->delete('api/v1/catalog/' . $resource . '/([1-9][0-9]*)', 'CatalogController::remove/' . $resource . '/$1', $options);
    $routes->put('api/v1/catalog/' . $resource . '/([1-9][0-9]*)', 'CatalogController::update/' . $resource . '/$1', $options);
}

$prices = ['filter' => 'api-session:prices.manage'];
$routes->get('api/v1/prices', 'PricesController::index', $prices);
$routes->post('api/v1/prices', 'PricesController::create', $prices);
$routes->post('api/v1/prices/preview', 'PricesController::preview', $prices);
$routes->post('api/v1/prices/([1-9][0-9]*)/end', 'PricesController::end/$1', $prices);
$routes->post('api/v1/prices/([1-9][0-9]*)/cancel', 'PricesController::cancel/$1', $prices);

$field = ['filter' => 'api-session'];
$routes->get('api/v1/work-logs', 'WorkLogsController::index', $field);
$routes->get('api/v1/work-logs/([1-9][0-9]*)', 'WorkLogsController::show/$1', $field);
$routes->post('api/v1/work-logs', 'WorkLogsController::create', $field);
$routes->put('api/v1/work-logs/([1-9][0-9]*)', 'WorkLogsController::update/$1', $field);
$routes->post('api/v1/work-logs/([1-9][0-9]*)/cancel', 'WorkLogsController::cancel/$1', $field);

$routes->get('api/v1/reports/work-logs', 'ReportsController::index', ['filter' => 'api-session:reports.view']);
$routes->get('api/v1/reports/work-logs/export', 'ReportsController::export', ['filter' => 'api-session:reports.view']);

$routes->get('api/v1/reports/options/(workers|clients|services|teams|sites)', 'ReportsController::options/$1', ['filter' => 'api-session:reports.view']);
$routes->get('api/v1/reports/options/(workers|clients|services|teams|sites)/([1-9][0-9]*)', 'ReportsController::options/$1/$2', ['filter' => 'api-session:reports.view']);

$routes->get('api/v1/field/options/(workers|teams|clients|sites|services|units|managers)', 'FieldCatalogController::options/$1', ['filter'=>'api-session']);
$routes->get('api/v1/field/options/(workers|teams|clients|sites|services|units|managers)/([1-9][0-9]*)', 'FieldCatalogController::options/$1/$2', ['filter'=>'api-session']);
$routes->post('api/v1/field/workers', 'FieldCatalogController::createWorker', ['filter'=>'api-session']);
$routes->get('api/v1/work-logs/([1-9][0-9]*)/close-preview', 'ProductionController::preview/$1', ['filter'=>'api-session']);
$routes->post('api/v1/work-logs/([1-9][0-9]*)/close', 'ProductionController::close/$1', ['filter'=>'api-session']);
$routes->get('api/v1/settlements', 'ProductionController::index', ['filter'=>'api-session:finance.manage']);
$routes->get('api/v1/settlements/pending', 'ProductionController::pending', ['filter'=>'api-session:finance.manage']);
$routes->get('api/v1/settlements/([1-9][0-9]*)', 'ProductionController::show/$1', ['filter'=>'api-session:finance.manage']);
$routes->post('api/v1/settlements', 'ProductionController::settle', ['filter'=>'api-session:finance.manage']);
$routes->post('api/v1/settlements/([1-9][0-9]*)/payments', 'ProductionController::pay/$1', ['filter'=>'api-session:finance.manage']);
$routes->get('api/v1/performance', 'PerformanceController::index', ['filter'=>'api-session:performance.view']);
$routes->get('api/v1/performance/([1-9][0-9]*)', 'PerformanceController::index/$1', ['filter'=>'api-session:performance.view']);

$routes->get('api/v1/settlements/options/workers', 'ReportsController::options/workers', ['filter'=>'api-session:finance.manage']);
$routes->get('api/v1/settlements/options/workers/([1-9][0-9]*)', 'ReportsController::options/workers/$1', ['filter'=>'api-session:finance.manage']);

$routes->get('api/v1/settlements/summary', 'ProductionController::summary', ['filter'=>'api-session:finance.manage']);
