<?php

/**
 * Base configuration — applies to every environment.
 *
 * Environment overrides live in config.{hostname}.php and are merged on top
 * of this file by Kirby. See config.localhost.php.example.
 */

define('RISTO_2026_VERSION', '0.1.0');

// Plugins load *after* config, so the class has to be pulled in by hand to be
// usable below. It is self-contained and touches no Kirby helpers during
// detection, which is what makes this safe this early in the boot.
require_once __DIR__ . '/../plugins/stream/src/Images.php';

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

    // Kirby's `im` (ImageMagick CLI) driver is deprecated as of Kirby 5.1 —
    // `imagick` (the PHP extension) is the supported one. Fall back to GD
    // where the extension is missing, so a dev machine without it still works.
    // HEIC input requires imagick + libheif; see the stream plugin's image
    // handling and docs/PLAN.md §1.4.
    'thumbs' => [
        'driver'    => extension_loaded('imagick') ? 'imagick' : 'gd',
        'quality'   => 72,
        'interlace' => true,

        // Detected, never asserted. Measured on this project:
        //
        //   Homebrew PHP 8.4 (CLI)  GD, AVIF write yes
        //   MAMP PHP 8.5.2 (web)    GD, AVIF write NO
        //   MAMP bundled imagick    ImageMagick 7.1.0-23, no heic/heif/avif
        //
        // Hardcoding 'avif' produces broken thumbnails on the machine this is
        // developed on. Risto\Stream\Images picks the best format the
        // installed driver can actually write: avif, else webp, else jpg.
        'format'    => Risto\Stream\Images::thumbFormat(),

        'srcsets' => [
            // Widths used by feed tiles. Keep in sync with the masonry
            // breakpoints in app.css.
            'tile' => [400, 800, 1200, 1600],
        ],
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

        'stream' => [
            // Posts fetched per feed page. Benchmarked: 30 posts costs ~41ms
            // of content reads at 20k total posts (docs/PLAN.md §1.3).
            'perPage' => 30,

            // Year bucket that new posts are filed into. Kept as an option so
            // the bucketing strategy can change without touching callers.
            'bucketFormat' => 'Y',

            // Uploads. Enforced server-side in the blueprint `accept` rule and
            // re-checked in the upload route; the client hint is cosmetic.
            'uploads' => [
                'maxSize'    => 25 * 1024 * 1024,
                'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'heif'],
            ],

            // HEIC/HEIF cannot pass through Kirby's thumb pipeline at all
            // (not in Image::$resizableTypes), so it is converted on upload.
            'convert' => [
                'from'    => ['heic', 'heif'],
                'to'      => 'avif',
                'quality' => 82,

                // Keep the untouched upload alongside the converted file.
                // An artist's archive should not be destroyed by a format
                // decision made at upload time.
                'keepOriginal' => true,
            ],
        ],
    ],

];
