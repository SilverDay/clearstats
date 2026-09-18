#!/usr/bin/env php
<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

/**
 * Builds the installable Joomla extension zip from this directory's source
 * files — run after any change to clearstats.xml, services/, src/, or
 * language/. Joomla requires clearstats.xml at the zip root (not nested in
 * a subfolder), which this script guarantees by zipping from inside the
 * plugin directory itself.
 *
 *   php package.php
 */

$root = __DIR__;
$manifest = simplexml_load_file($root . '/clearstats.xml');
if ($manifest === false) {
    fwrite(STDERR, "Could not read clearstats.xml\n");
    exit(1);
}

$version = (string) $manifest->version;
$distDir = $root . '/dist';
if (!is_dir($distDir) && !mkdir($distDir, 0777, true) && !is_dir($distDir)) {
    fwrite(STDERR, "Could not create dist/\n");
    exit(1);
}

$zipPath = sprintf('%s/plg_system_clearstats-%s.zip', $distDir, $version !== '' ? $version : 'dev');
if (file_exists($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Could not create $zipPath\n");
    exit(1);
}

$included = ['clearstats.xml', 'README.md', 'services', 'src', 'language'];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);

foreach ($iterator as $file) {
    $relative = substr($file->getPathname(), strlen($root) + 1);
    $topLevel = explode('/', $relative)[0];
    if (!in_array($topLevel, $included, true)) {
        continue;
    }

    if ($file->isDir()) {
        $zip->addEmptyDir($relative);
    } else {
        $zip->addFile($file->getPathname(), $relative);
    }
}

$zip->close();

fwrite(STDOUT, sprintf("Built %s (%d bytes)\n", $zipPath, filesize($zipPath)));
