<?php

namespace Risto\Api;

use Kirby\Exception\InvalidArgumentException;
use Kirby\Http\Response;
use Risto\Stream\Drawing;
use Throwable;

/**
 * Reading and replacing the drawing layer.
 */
final class DrawingRoutes
{
    /**
     * The editable source, for the editor only.
     *
     * Visitors never call this — the SVG is already inlined in the page.
     */
    public static function show(): Response
    {
        $drawing = Drawing::read();

        return Response::json([
            'json'   => $drawing['json'],
            'width'  => $drawing['width'],
            'height' => $drawing['height'],
        ], 200, false, ['Cache-Control' => 'no-store, private']);
    }

    /**
     * Replaces the layer. Artist only.
     */
    public static function update(): Response
    {
        $kirby = kirby();
        $user  = $kirby->user();

        if ($user === null) {
            return Respond::fail('unauthenticated', 'Please log in first.', 401);
        }

        if ($guard = Respond::guardCsrf()) {
            return $guard;
        }

        // Same permission as posting: drawing is authoring, not viewing.
        if ($user->role()->permissions()->for('pages', 'update') !== true) {
            return Respond::fail('forbidden', 'Your account cannot draw.', 403);
        }

        $request = $kirby->request();

        try {
            Drawing::write(
                json: (string) $request->get('json', ''),
                svg: (string) $request->get('svg', ''),
                width: (int) $request->get('width', 0),
                height: (int) $request->get('height', 0),
            );
        } catch (InvalidArgumentException $e) {
            return Respond::fail('invalid', $e->getMessage(), 422);
        } catch (Throwable) {
            return Respond::fail('failed', 'The drawing could not be saved.', 500);
        }

        return Respond::ok();
    }
}
