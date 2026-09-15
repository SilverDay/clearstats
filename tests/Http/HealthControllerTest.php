<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Http;

use ClearStats\Http\HealthController;
use ClearStats\Ingestion\EventQueue;
use ClearStats\Tests\TestDatabase;
use PHPUnit\Framework\TestCase;

final class HealthControllerTest extends TestCase
{
    private function queue(): EventQueue
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);

        return new EventQueue($redis);
    }

    private function render(HealthController $controller): array
    {
        ob_start();
        $controller->index();

        return (array) json_decode((string) ob_get_clean(), true);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testReportsOkWhenDependenciesRespond(): void
    {
        $body = $this->render(new HealthController(TestDatabase::create()->pdo(), $this->queue()));

        $this->assertSame('ok', $body['status']);
    }

    public function testReportsDegradedWhenTheDatabaseIsUnavailable(): void
    {
        $body = $this->render(new HealthController(null, $this->queue()));

        $this->assertSame('degraded', $body['status']);
    }

    public function testCountsAreHiddenWithoutAValidToken(): void
    {
        $controller = new HealthController(TestDatabase::create()->pdo(), $this->queue(), 'secret-token');

        $anonymous = $this->render($controller);
        $this->assertArrayNotHasKey('queue', $anonymous);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer wrong-token';
        $this->assertArrayNotHasKey('queue', $this->render($controller));
    }

    public function testCountsAreExposedWithAValidToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret-token';
        $body = $this->render(new HealthController(TestDatabase::create()->pdo(), $this->queue(), 'secret-token'));

        $this->assertArrayHasKey('queue', $body);
        $this->assertArrayHasKey('rejected', $body['queue']);
    }

    public function testDetailStaysHiddenWhenNoTokenIsConfigured(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ';
        $body = $this->render(new HealthController(TestDatabase::create()->pdo(), $this->queue()));

        $this->assertArrayNotHasKey('queue', $body);
    }
}
