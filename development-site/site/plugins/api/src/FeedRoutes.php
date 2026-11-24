<?php

namespace Risto\Api;

use Kirby\Http\Response;
use Risto\Stream\Feed;

/**
 * Serving further pages of the feed.
 */
final class FeedRoutes
{
    /**
     * Returns the next page of tiles, already rendered.
     *
     * HTML rather than JSON data: the server stays the single authority on
     * how a tile looks, so there is no second renderer in JavaScript to drift
     * out of step with the snippets.
     */
    public static function page(): Response
    {
        $cursor = kirby()->request()->get('cursor');
        $cursor = is_string($cursor) ? $cursor : null;

        $feed = Feed::instance()->posts($cursor);

        $html = '';

        foreach ($feed['posts'] as $post) {
            $html .= snippet('post/tile', ['post' => $post], return: true);
        }

        return Response::json([
            'html'       => $html,
            'nextCursor' => $feed['nextCursor'],
            'count'      => $feed['posts']->count(),
        ], 200, false, [
            // Not cached: phase 6 adds per-post controls for the artist, at
            // which point a shared cache entry would leak them to visitors.
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
