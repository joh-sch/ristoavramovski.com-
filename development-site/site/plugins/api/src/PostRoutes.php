<?php

namespace Risto\Api;

use Kirby\Exception\InvalidArgumentException;
use Kirby\Http\Response;
use Risto\Stream\Posts;
use Throwable;

/**
 * Creating posts from the front end.
 */
final class PostRoutes
{
    public static function create(): Response
    {
        $kirby = kirby();
        $user  = $kirby->user();

        if ($user === null) {
            return Respond::fail(
                code: 'unauthenticated',
                message: 'Please log in first.',
                status: 401,
            );
        }

        if ($guard = Respond::guardCsrf(redirect: url('compose'))) {
            return $guard;
        }

        // Belt and braces: the role blueprint already withholds page.create
        // from anyone but the artist, but an endpoint should not depend on a
        // blueprint staying that way.
        if ($user->role()->permissions()->for('pages', 'create') !== true) {
            return Respond::fail(
                code: 'forbidden',
                message: 'Your account cannot add posts.',
                status: 403,
            );
        }

        $request = $kirby->request();

        $input = [
            'type' => (string) $request->get('type', ''),
            'text' => (string) $request->get('text', ''),
            'url'  => (string) $request->get('url', ''),
            'size' => (string) $request->get('size', 'm'),
            'alt'  => (string) $request->get('alt', ''),
        ];

        $upload = $_FILES['image'] ?? null;

        try {
            $post = Posts::create($input, $upload);
        } catch (InvalidArgumentException $e) {
            // Validation messages are written for the artist and are safe to
            // show verbatim.
            return Respond::fail(
                code: 'invalid',
                message: $e->getMessage(),
                status: 422,
                redirect: url('compose'),
            );
        } catch (Throwable) {
            return Respond::fail(
                code: 'failed',
                message: 'The post could not be saved.',
                status: 500,
                redirect: url('compose'),
            );
        }

        return Respond::ok([
            'post' => [
                'id'   => $post->id(),
                'num'  => (string) $post->num(),
                'type' => $post->intendedTemplate()->name(),
            ],
        ]);
    }
}
