<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

final class AuthSession
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            session_set_cookie_params([
                'httponly' => true,
                'secure' => $this->isHttps(),
                'samesite' => 'Lax',
                'path' => '/',
            ]);
            session_start();
        }
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION['auth_user_id']) && is_string($_SESSION['auth_user_id']) && $_SESSION['auth_user_id'] !== '';
    }

    public function userId(): ?string
    {
        return $this->isAuthenticated() ? (string) $_SESSION['auth_user_id'] : null;
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public function validCsrfToken(string $token): bool
    {
        return $token !== '' && hash_equals($this->csrfToken(), $token);
    }

    public function login(string $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['auth_user_id'] = $userId;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    private function isHttps(): bool
    {
        return ($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }
}
