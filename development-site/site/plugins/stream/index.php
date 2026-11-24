<?php

use Kirby\Cms\App as Kirby;
use Risto\Stream\Feed;
use Risto\Stream\Stream;

// Paths are relative: load() resolves them against the base dir argument.
load([
    'Risto\\Stream\\Feed'   => 'src/Feed.php',
    'Risto\\Stream\\Images' => 'src/Images.php',
    'Risto\\Stream\\Posts'  => 'src/Posts.php',
    'Risto\\Stream\\Stream' => 'src/Stream.php',
], __DIR__);

Kirby::plugin('risto/stream', [

    'pageMethods' => [
        /**
         * Is this page one of the stream's post types?
         */
        'isStreamPost' => function (): bool {
            return Stream::isPost($this);
        },
    ],

    'hooks' => [
        /**
         * The date can be edited in the Panel after a post is created, which
         * would leave the folder number pointing at the old moment and the
         * post stranded in the wrong place in the feed.
         *
         * syncSortkey() returns false when nothing changed, which is what
         * stops this hook from recursing through its own update().
         */
        'page.update:after' => function ($newPage) {
            if (Stream::isPost($newPage) === false) {
                return;
            }

            Stream::syncSortkey($newPage);
        },
    ],

    'siteMethods' => [
        /**
         * One page of the feed, newest first.
         *
         * @return array{posts: \Kirby\Cms\Pages, nextCursor: string|null}
         */
        'feed' => function (string|null $cursor = null): array {
            return Feed::instance()->posts($cursor);
        },
    ],

]);
