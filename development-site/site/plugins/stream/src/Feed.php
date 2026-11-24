<?php

namespace Risto\Stream;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;

/**
 * Reads pages of the post stream.
 *
 * ── PERFORMANCE CONTRACT ─────────────────────────────────────────────────
 * Every query here orders and paginates by `num`, which Kirby reads from the
 * directory name without opening a content file.
 *
 * Measured on this codebase (docs/PLAN.md §1.3), at 20,000 posts:
 *
 *     sort by num + take 30      56 ms
 *     read content of those 30   41 ms
 *     filter by a content field  1,856 ms   ← 33x worse, and linear
 *
 * Nothing in this class may sort or filter the full collection by a content
 * field. Cross-cutting queries (by type, tag, search) belong in the feed
 * index, not here.
 * ─────────────────────────────────────────────────────────────────────────
 */
final class Feed
{
    public function __construct(
        private readonly int $perPage,
    ) {
    }

    public static function instance(): self
    {
        return new self(
            perPage: max(1, (int) option('risto.stream.perPage', 30)),
        );
    }

    /**
     * One page of posts, newest first.
     *
     * Cursor-based rather than offset-based on purpose: offsets both degrade
     * with depth and silently duplicate or skip posts when a new post lands
     * at the head of the feed while someone is scrolling.
     *
     * @param  string|null $cursor `num` of the last post already shown
     * @return array{posts: Pages, nextCursor: string|null}
     */
    public function posts(string|null $cursor = null): array
    {
        $cursorNum = self::parseCursor($cursor);
        $limit     = $this->perPage;

        // Collect one more than needed: its existence is what tells us
        // whether there is another page, without a second count query.
        $collected = [];

        foreach ($this->buckets($cursorNum) as $bucket) {
            $posts = $bucket
                ->children()
                ->listed()
                ->sortBy('num', 'desc');

            foreach ($posts as $post) {
                // Skip anything at or above the cursor. Comparing num only —
                // still no content read.
                if ($cursorNum !== null && (int) $post->num() >= $cursorNum) {
                    continue;
                }

                $collected[] = $post;

                if (count($collected) > $limit) {
                    break 2;
                }
            }
        }

        $hasMore = count($collected) > $limit;

        if ($hasMore === true) {
            array_pop($collected);
        }

        $last = end($collected) ?: null;

        return [
            'posts'      => new Pages($collected),
            'nextCursor' => $hasMore && $last ? (string) $last->num() : null,
        ];
    }

    /**
     * Buckets that could still contain posts older than the cursor.
     *
     * A cursor of 20261124143000 means every bucket newer than 2026 is
     * already exhausted, so they are never even opened.
     */
    private function buckets(int|null $cursorNum): Pages
    {
        $buckets = Stream::buckets();

        if ($cursorNum === null) {
            return $buckets;
        }

        $year = substr((string) $cursorNum, 0, 4);

        return $buckets->filter(fn (Page $page) => $page->slug() <= $year);
    }

    /**
     * Validates a cursor coming from a query string.
     *
     * Returns null for anything malformed rather than throwing — a junk
     * cursor should quietly yield the first page, not a 500.
     */
    private static function parseCursor(string|null $cursor): int|null
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $cursor = trim($cursor);

        // Any digit string is acceptable: the cursor is only ever compared
        // numerically against `num`. Deliberately not pinned to the current
        // 14-digit YmdHis width, so that changing the num definition degrades
        // into a harmless reset rather than a silently broken feed.
        if (ctype_digit($cursor) === false || strlen($cursor) > 20) {
            return null;
        }

        return (int) $cursor;
    }
}
