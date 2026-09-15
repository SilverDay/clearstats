<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

final class Router
{
    /**
     * @return array{controller: string, action: string}|null
     */
    public function route(string $method, string $path): ?array
    {
        $normalized = rtrim($path, '/');
        if ($normalized === '') {
            $normalized = '/';
        }

        $routes = [
            'GET /' => ['controller' => 'HomeController', 'action' => 'index'],
            'GET /health' => ['controller' => 'HealthController', 'action' => 'index'],
            'GET /about' => ['controller' => 'InfoController', 'action' => 'about'],
            'GET /faq' => ['controller' => 'InfoController', 'action' => 'faq'],
            'GET /imprint' => ['controller' => 'InfoController', 'action' => 'imprint'],
            'GET /privacy' => ['controller' => 'InfoController', 'action' => 'privacy'],
            'GET /terms' => ['controller' => 'InfoController', 'action' => 'terms'],
            'GET /login' => ['controller' => 'AuthController', 'action' => 'login'],
            'POST /login' => ['controller' => 'AuthController', 'action' => 'login'],
            'GET /forgot-password' => ['controller' => 'AuthController', 'action' => 'forgotPassword'],
            'POST /forgot-password' => ['controller' => 'AuthController', 'action' => 'forgotPassword'],
            'POST /logout' => ['controller' => 'AuthController', 'action' => 'logout'],
            'GET /password' => ['controller' => 'AuthController', 'action' => 'passwordChange'],
            'POST /password' => ['controller' => 'AuthController', 'action' => 'passwordChange'],
            'GET /dashboard' => ['controller' => 'DashboardController', 'action' => 'index'],
            'GET /sites' => ['controller' => 'SiteController', 'action' => 'index'],
            'GET /sites/new' => ['controller' => 'SiteController', 'action' => 'create'],
            'POST /sites/new' => ['controller' => 'SiteController', 'action' => 'create'],
            'GET /sites/edit' => ['controller' => 'SiteController', 'action' => 'edit'],
            'POST /sites/edit' => ['controller' => 'SiteController', 'action' => 'edit'],
            'GET /sites/install' => ['controller' => 'SiteController', 'action' => 'install'],
            'GET /users' => ['controller' => 'UserController', 'action' => 'index'],
            'GET /users/new' => ['controller' => 'UserController', 'action' => 'create'],
            'POST /users/new' => ['controller' => 'UserController', 'action' => 'create'],
            'GET /users/assign' => ['controller' => 'UserController', 'action' => 'assign'],
            'POST /users/assign' => ['controller' => 'UserController', 'action' => 'assign'],
            'POST /api/event' => ['controller' => 'EventController', 'action' => 'handle'],
            'OPTIONS /api/event' => ['controller' => 'EventController', 'action' => 'handle'],
        ];

        $key = strtoupper($method) . ' ' . $normalized;

        return $routes[$key] ?? null;
    }
}
