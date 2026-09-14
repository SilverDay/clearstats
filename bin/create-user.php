#!/usr/bin/env php
<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';
$email = strtolower(trim((string) ($argv[1] ?? '')));
$role = strtolower(trim((string) ($argv[2] ?? 'admin')));

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Usage: php bin/create-user.php EMAIL [admin|editor|viewer]\n");
    exit(2);
}

if (!in_array($role, ['admin', 'editor', 'viewer'], true)) {
    fwrite(STDERR, "Invalid role. Use admin, editor, or viewer.\n");
    exit(2);
}

$database = new ClearStats\Db\Database($config['db']);
$pdo = $database->pdo();
$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount === 0 && $role !== 'admin') {
    fwrite(STDERR, "The first user must have the admin role.\n");
    exit(2);
}

$password = promptSecret('Password: ');
$confirmation = promptSecret('Confirm password: ');
if ($password === '' || !hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Passwords are empty or do not match.\n");
    exit(2);
}
if (strlen($password) < 12) {
    fwrite(STDERR, "Password must contain at least 12 characters.\n");
    exit(2);
}

$statement = $pdo->prepare(
    'INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)',
);
$statement->execute([
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => $role,
]);

fwrite(STDOUT, sprintf("Created %s user %s (id %s).\n", $role, $email, $pdo->lastInsertId()));

function promptSecret(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $isWindows = PHP_OS_FAMILY === 'Windows';
    if (!$isWindows) {
        shell_exec('stty -echo');
    }
    $value = trim((string) fgets(STDIN));
    if (!$isWindows) {
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    }

    return $value;
}
