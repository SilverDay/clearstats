<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

use ClearStats\Ingestion\EventQueue;
use PDO;

/**
 * Liveness and queue health for monitoring.
 *
 * Unauthenticated callers only ever see ok/degraded. Counts are gated behind a
 * bearer token because queue depth and rejection volume describe traffic on the
 * tracked sites. With no token configured the detail is simply unavailable.
 */
final class HealthController
{
    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?EventQueue $queue = null,
        private readonly string $token = '',
    ) {}

    public function index(): void
    {
        $databaseOk = $this->databaseOk();
        $queueOk = $this->queueOk();
        $healthy = $databaseOk && $queueOk;

        http_response_code($healthy ? 200 : 503);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $body = ['status' => $healthy ? 'ok' : 'degraded'];

        if ($this->authorised()) {
            $body['checks'] = ['database' => $databaseOk, 'redis' => $queueOk];
            if ($queueOk && $this->queue !== null) {
                $body['queue'] = [
                    'pending' => $this->queue->pendingCount(),
                    'processing' => $this->queue->processingCount(),
                    'rejected' => $this->queue->rejectedCount(),
                ];
            }
        }

        echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function authorised(): bool
    {
        if ($this->token === '') {
            return false;
        }

        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (!str_starts_with($header, 'Bearer ')) {
            return false;
        }

        return hash_equals($this->token, substr($header, 7));
    }

    private function databaseOk(): bool
    {
        if ($this->pdo === null) {
            return false;
        }

        try {
            return $this->pdo->query('SELECT 1') !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function queueOk(): bool
    {
        if ($this->queue === null) {
            return false;
        }

        try {
            $this->queue->pendingCount();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
