#!/usr/bin/env php
<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

/**
 * Builds public/js/track.js — the file actually served to visitors, since
 * every already-installed site's <script src> points at that exact URL —
 * from assets/track.js, the readable, tested source.
 *
 *   php bin/build-track-js.php
 *
 * Requires Node/npm (uses `npx terser`; not a runtime dependency of the PHP
 * app itself, only of this one build step). Run after editing
 * assets/track.js and commit both files — public/js/track.js is not
 * regenerated automatically anywhere else.
 */

$root = dirname(__DIR__);
$sourcePath = $root . '/assets/track.js';
$outputPath = $root . '/public/js/track.js';

$source = file_get_contents($sourcePath);
if ($source === false) {
    fwrite(STDERR, "Could not read $sourcePath\n");
    exit(1);
}

// Fixed argv array, not a shell string — no interpolation, nothing to escape.
$command = ['npx', '--yes', 'terser', $sourcePath, '--compress', '--mangle'];
$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
if (!is_resource($process)) {
    fwrite(STDERR, "Could not start terser (is Node/npm installed?)\n");
    exit(1);
}

$minified = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($exitCode !== 0 || $minified === false || trim($minified) === '') {
    fwrite(STDERR, "terser failed (exit $exitCode):\n$stderr\n");
    exit(1);
}

$header = "/* ClearStats tracking script — MIT — https://github.com/SilverDay/clearstats */\n";
$built = $header . trim($minified) . "\n";

if (file_put_contents($outputPath, $built) === false) {
    fwrite(STDERR, "Could not write $outputPath\n");
    exit(1);
}
chmod($outputPath, 0644);

$sourceBytes = strlen($source);
$builtBytes = strlen($built);
fwrite(STDOUT, sprintf(
    "Built %s: %d bytes -> %d bytes (%.0f%% of source)\n",
    $outputPath,
    $sourceBytes,
    $builtBytes,
    ($builtBytes / $sourceBytes) * 100,
));
