<?php

require __DIR__ . '/vendor/autoload.php';

/**
 * Writable runtime state (accounts, cache, sessions) lives in storage/
 * rather than inside site/, so that site/ stays a pure, deployable
 * source directory. These roots must match the directories created by
 * `npm run init-kirby` and the paths blocked in .htaccess.
 */
$storage = __DIR__ . '/storage';

$kirby = new Kirby([
    'roots' => [
        'index'    => __DIR__,
        'storage'  => $storage,
        'accounts' => $storage . '/accounts',
        'cache'    => $storage . '/cache',
        'sessions' => $storage . '/sessions',
    ],
]);

echo $kirby->render();
