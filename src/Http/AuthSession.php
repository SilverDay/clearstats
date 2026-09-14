<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

final class AuthSession
{
    private const IDLE_TIMEOUT = 1800;
    private const ABSOLUTE_TIMEOUT = 28800;
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
        if (!isset($_SESSION['auth_user_id'], $_SESSION['auth_issued_at'], $_SESSION['auth_last_seen'])) {
            return false;
        }
        $now = time();
        if ($now - (int) $_SESSION['auth_last_seen'] > self::IDLE_TIMEOUT || $now - (int) $_SESSION['auth_issued_at'] > self::ABSOLUTE_TIMEOUT) {
            $this->logout();
            return false;
        }
        $_SESSION['auth_last_seen'] = $now;
        return is_string($_SESSION['auth_user_id']) && $_SESSION['auth_user_id'] !== '';
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
        $_SESSION['auth_issued_at'] = time();
        $_SESSION['auth_last_seen'] = time();
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    private function isHttps(): bool
    {
        return ($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }
}
