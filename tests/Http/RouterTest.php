<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Http;

use ClearStats\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testRoutesLoginAndDashboard(): void
    {
        $router = new Router();

        $this->assertSame(['controller' => 'HomeController', 'action' => 'index'], $router->route('GET', '/'));
        $this->assertSame(['controller' => 'InfoController', 'action' => 'about'], $router->route('GET', '/about'));
        $this->assertSame(['controller' => 'InfoController', 'action' => 'faq'], $router->route('GET', '/faq'));
        $this->assertSame(['controller' => 'InfoController', 'action' => 'imprint'], $router->route('GET', '/imprint'));
        $this->assertSame(['controller' => 'InfoController', 'action' => 'privacy'], $router->route('GET', '/privacy'));
        $this->assertSame(['controller' => 'InfoController', 'action' => 'terms'], $router->route('GET', '/terms'));
        $this->assertSame(['controller' => 'AuthController', 'action' => 'login'], $router->route('GET', '/login'));
        $this->assertSame(['controller' => 'AuthController', 'action' => 'login'], $router->route('POST', '/login'));
        $this->assertSame(['controller' => 'AuthController', 'action' => 'forgotPassword'], $router->route('GET', '/forgot-password'));
        $this->assertSame(['controller' => 'AuthController', 'action' => 'forgotPassword'], $router->route('POST', '/forgot-password'));
        $this->assertSame(['controller' => 'EventController', 'action' => 'handle'], $router->route('OPTIONS', '/api/event'));
        $this->assertSame(['controller' => 'AuthController', 'action' => 'logout'], $router->route('POST', '/logout'));
        $this->assertNull($router->route('GET', '/logout'));
        $this->assertSame(['controller' => 'DashboardController', 'action' => 'index'], $router->route('GET', '/dashboard'));
        $this->assertSame(['controller' => 'DashboardController', 'action' => 'index'], $router->route('GET', '/dashboard/'));
        $this->assertSame(['controller' => 'SiteController', 'action' => 'index'], $router->route('GET', '/sites'));
        $this->assertSame(['controller' => 'SiteController', 'action' => 'create'], $router->route('GET', '/sites/new'));
        $this->assertSame(['controller' => 'SiteController', 'action' => 'install'], $router->route('GET', '/sites/install'));
        $this->assertSame(['controller' => 'UserController', 'action' => 'index'], $router->route('GET', '/users'));
    }

    public function testRejectsUnknownRoutes(): void
    {
        $router = new Router();

        $this->assertNull($router->route('GET', '/unknown'));
    }
}
