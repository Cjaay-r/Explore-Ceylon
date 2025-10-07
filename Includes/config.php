<?php
// Includes/config.php

// Automatically detect your local base path (e.g. /ceylon/)
if (!defined('WEB_BASE')) {
    $docRootReal = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $projRootReal = realpath(dirname(__DIR__));

    $docRoot = $docRootReal ? str_replace('\\', '/', rtrim($docRootReal, '/')) : '';
    $projRoot = $projRootReal ? str_replace('\\', '/', rtrim($projRootReal, '/')) : '';

    $webBase = '/';

    if ($docRoot && $projRoot && strpos($projRoot, $docRoot) === 0) {
        $base = trim(substr($projRoot, strlen($docRoot)), '/');
        $webBase = '/' . ($base !== '' ? $base . '/' : '');
    } else {
        // fallback if detection fails
        $webBase = '/ceylon/';
    }

    define('WEB_BASE', $webBase);
}

// Optional helper function for cleaner links
if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return WEB_BASE . ltrim($path, '/');
    }
}
