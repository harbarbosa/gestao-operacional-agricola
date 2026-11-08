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
