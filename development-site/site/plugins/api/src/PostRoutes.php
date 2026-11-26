<?php

namespace Risto\Api;

use Kirby\Exception\InvalidArgumentException;
use Kirby\Http\Response;
use Risto\Stream\Posts;
use Risto\Stream\Projects;
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
            'title' => (string) $request->get('projectTitle', ''),
            'requestId' => (string) $request->get('requestId', ''),
        ];

        $upload = $_FILES['image'] ?? null;

        try {
            if ($input['type'] === 'project') {
                $rawLayers = (string) $request->get('projectLayers', '[]');
                $decoded = json_decode($rawLayers, true);

                if (is_array($decoded) === false) {
                    throw new InvalidArgumentException(message: 'Invalid project layers.');
                }

                $layers = [];

                foreach ($decoded as $index => $layer) {
                    if (!is_array($layer)) {
                        throw new InvalidArgumentException(message: 'Invalid project layer.');
                    }

                    $type = (string) ($layer['type'] ?? '');
                    $layers[] = [
                        'type' => $type,
                        'text' => (string) ($layer['text'] ?? ''),
                        'alt' => (string) ($layer['alt'] ?? ''),
                        'upload' => $_FILES['project-image-' . $index] ?? null,
                    ];
                }

                $post = Projects::create($input, $layers);
            } else {
                $post = Posts::create($input, $upload);
            }
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

    public static function deleteProject(string $id): Response
    {
        $user = kirby()->user();

        if ($user === null) {
            return Respond::fail(
                code: 'unauthenticated',
                message: 'Please log in first.',
                status: 401,
            );
        }

        if ($guard = Respond::guardCsrf()) {
            return $guard;
        }

        if ($user->role()->permissions()->for('pages', 'delete') !== true) {
            return Respond::fail(
                code: 'forbidden',
                message: 'Your account cannot delete projects.',
                status: 403,
            );
        }

        $project = page($id);

        if ($project === null) {
            return Respond::ok(['deleted' => false]);
        }

        try {
            Projects::delete($project);
        } catch (InvalidArgumentException $e) {
            return Respond::fail(
                code: 'invalid',
                message: $e->getMessage(),
                status: 422,
            );
        } catch (Throwable) {
            return Respond::fail(
                code: 'failed',
                message: 'The project could not be deleted.',
                status: 500,
            );
        }

        return Respond::ok(['deleted' => true]);
    }
}
