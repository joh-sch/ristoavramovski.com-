<?php

use Kirby\Cms\App as Kirby;
use Risto\Stream\Feed;
use Risto\Stream\Stream;

// Paths are relative: load() resolves them against the base dir argument.
load([
    'Risto\\Stream\\Drawing' => 'src/Drawing.php',
    'Risto\\Stream\\Feed'   => 'src/Feed.php',
    'Risto\\Stream\\FeedIndex' => 'src/FeedIndex.php',
    'Risto\\Stream\\Images' => 'src/Images.php',
    'Risto\\Stream\\Posts'  => 'src/Posts.php',
    'Risto\\Stream\\Projects' => 'src/Projects.php',
    'Risto\\Stream\\Stream' => 'src/Stream.php',
], __DIR__);

Kirby::plugin('risto/stream', [

    'routes' => [
        [
            'pattern' => 'projects/(:any)',
            'method' => 'GET',
            'action' => function (string $slug) {
                $root = page('projects');

                if ($root === null) {
                    return null;
                }

                foreach ($root->children() as $bucket) {
                    $project = $bucket->children()->listed()
                        ->filter(fn ($candidate) => $candidate->slug() === $slug)
                        ->first();

                    if ($project !== null) {
                        return $project->render();
                    }
                }

                return null;
            },
        ],
    ],

    'pageMethods' => [
        /**
         * Is this page one of the stream's post types?
         */
        'isStreamPost' => function (): bool {
            return Stream::isPost($this);
        },
    ],

    'hooks' => [
        'page.create:before' => function ($page) {
            if (Stream::isPost($page) === true
                || $page->intendedTemplate()->name() === 'project'
            ) {
                \Risto\Stream\FeedIndex::markDirty();
            }

            if ($page->intendedTemplate()->name() === 'project') {
                foreach (\Risto\Stream\Projects::buckets() as $bucket) {
                    foreach ($bucket->childrenAndDrafts() as $project) {
                        if ($project->slug() === $page->slug()) {
                            throw new \Kirby\Exception\DuplicateException(
                                message: 'That project slug is already in use.'
                            );
                        }
                    }
                }
            }
        },
        'page.create:after' => function ($page) {
            if (Stream::isPost($page) === true
                || $page->intendedTemplate()->name() === 'project'
            ) {
                \Risto\Stream\FeedIndex::rebuild();
            }
        },
        'page.update:before' => function ($page) {
            if (Stream::isPost($page) === true
                || $page->intendedTemplate()->name() === 'project'
            ) {
                \Risto\Stream\FeedIndex::markDirty();
            }
        },
        /**
         * The date can be edited in the Panel after a post is created, which
         * would leave the folder number pointing at the old moment and the
         * post stranded in the wrong place in the feed.
         *
         * syncSortkey() returns false when nothing changed, which is what
         * stops this hook from recursing through its own update().
         */
        'page.update:after' => function ($newPage) {
            if (Stream::isPost($newPage) === false
                && $newPage->intendedTemplate()->name() !== 'project'
            ) {
                return;
            }

            if (Stream::isPost($newPage) === true) {
                Stream::syncSortkey($newPage);
            }

            if ($newPage->intendedTemplate()->name() === 'project') {
                \Risto\Stream\Projects::syncSortkey($newPage);
            }

            \Risto\Stream\FeedIndex::rebuild();
        },
        'page.delete:after' => function ($status, $page) {
            if (Stream::isPost($page) === true
                || $page->intendedTemplate()->name() === 'project'
            ) {
                \Risto\Stream\FeedIndex::rebuild();
            }
        },
        'page.changeStatus:before' => function ($page) {
            if (Stream::isPost($page) === true
                || $page->intendedTemplate()->name() === 'project'
            ) {
                \Risto\Stream\FeedIndex::markDirty();
            }
        },
        'page.changeStatus:after' => function ($newPage) {
            if (Stream::isPost($newPage) === true
                || $newPage->intendedTemplate()->name() === 'project'
            ) {
                \Risto\Stream\FeedIndex::rebuild();
            }
        },
        'page.move:before' => function ($page) {
            if ($page->intendedTemplate()->name() === 'project') {
                \Risto\Stream\FeedIndex::markDirty();
            }
        },
        'page.move:after' => function ($newPage) {
            if ($newPage->intendedTemplate()->name() === 'project') {
                \Risto\Stream\FeedIndex::rebuild();
            }
        },
        'page.changeSlug:before' => function ($page, $slug) {
            if ($page->intendedTemplate()->name() !== 'project') {
                return;
            }

            foreach (\Risto\Stream\Projects::buckets() as $bucket) {
                foreach ($bucket->childrenAndDrafts() as $project) {
                    if ($project->is($page) === false && $project->slug() === $slug) {
                        throw new \Kirby\Exception\DuplicateException(
                            message: 'That project slug is already in use.'
                        );
                    }
                }
            }
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
