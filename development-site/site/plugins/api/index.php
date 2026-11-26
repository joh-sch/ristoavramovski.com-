<?php

use Kirby\Cms\App as Kirby;
use Risto\Api\DrawingRoutes;
use Risto\Api\FeedRoutes;
use Risto\Api\PostRoutes;
use Risto\Api\Session;

load([
    'Risto\\Api\\DrawingRoutes' => 'src/DrawingRoutes.php',
    'Risto\\Api\\FeedRoutes' => 'src/FeedRoutes.php',
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

        [
            'pattern' => '_api/projects/:id',
            'method'  => 'DELETE',
            'action'  => fn (string $id) => PostRoutes::deleteProject($id),
        ],

        [
            'pattern' => '_api/feed',
            'method'  => 'GET',
            'action'  => fn () => FeedRoutes::page(),
        ],

        [
            'pattern' => '_api/drawing',
            'method'  => 'GET',
            'action'  => fn () => DrawingRoutes::show(),
        ],

        [
            'pattern' => '_api/drawing',
            'method'  => 'POST',
            'action'  => fn () => DrawingRoutes::update(),
        ],

    ],

]);
