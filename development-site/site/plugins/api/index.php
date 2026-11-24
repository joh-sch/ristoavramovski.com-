<?php

use Kirby\Cms\App as Kirby;
use Risto\Api\PostRoutes;
use Risto\Api\Session;

load([
    'Risto\\Api\\PostRoutes' => 'src/PostRoutes.php',
    'Risto\\Api\\Respond'    => 'src/Respond.php',
    'Risto\\Api\\Session'    => 'src/Session.php',
], __DIR__);

Kirby::plugin('risto/api', [

    /**
     * Routes live under /_api/ because page slugs cannot begin with an
     * underscore, so nothing the artist ever creates can shadow them. It also
     * stays clear of Kirby's own reserved /api slug.
     */
    'routes' => [

        [
            'pattern' => '_api/session/token',
            'method'  => 'GET',
            'action'  => fn () => Session::token(),
        ],

        [
            'pattern' => '_api/session',
            'method'  => 'POST',
            'action'  => fn () => Session::create(),
        ],

        [
            // DELETE for fetch() clients...
            'pattern' => '_api/session',
            'method'  => 'DELETE',
            'action'  => fn () => Session::destroy(),
        ],

        [
            // ...and a POST alias, because HTML forms cannot send DELETE.
            'pattern' => '_api/session/end',
            'method'  => 'POST',
            'action'  => fn () => Session::destroy(),
        ],

        [
            'pattern' => '_api/posts',
            'method'  => 'POST',
            'action'  => fn () => PostRoutes::create(),
        ],

    ],

]);
