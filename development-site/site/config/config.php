<?php

/**
 * Base configuration — applies to every environment.
 *
 * Environment overrides live in config.{hostname}.php and are merged on top
 * of this file by Kirby. See config.localhost.php.example.
 */

define('RISTO_2026_VERSION', '0.0.0');

return [

    // ── Environment ──────────────────────────────────────────────────────────

    'debug' => false,

    'panel' => [
        // Enabled only in the local config, and only until the first
        // account exists. Never enable this on a public server.
        'install' => false,
    ],

    'languages' => false,

    // ── Content ──────────────────────────────────────────────────────────────

    'date' => [
        // Locale-aware date formatting via PHP's intl extension.
        // Fall back to 'date' if intl is unavailable on the target server.
        'handler' => 'intl',
    ],

    'smartypants' => false,

    // ── Images ───────────────────────────────────────────────────────────────

    'thumbs' => [
        'quality'  => 80,
        'interlace' => true,
        // Uncomment to serve WebP everywhere — requires GD or ImageMagick
        // with WebP support on the target server.
        // 'format' => 'webp',
    ],

    // ── Caching ──────────────────────────────────────────────────────────────

    // Off by default: enable per-environment once the content model is stable.
    'cache' => [
        'pages' => [
            'active' => false,
        ],
    ],

    // ── Project options ──────────────────────────────────────────────────────

    'risto' => [
        // Injects the livereload client into the footer. Local only.
        'livereload' => false,
    ],

];
