<?php

/**
 * The stream.
 *
 * Calling $kirby->user() is load-bearing for caching, not just for the view:
 * it marks the response cookie-dependent, so anonymous visitors get cached
 * HTML and the artist always gets a fresh render. See docs/PLAN.md §1.1.
 *
 * Layout and pagination live in the stream-feed component.
 */

$user = $kirby->user();

// ?cursor= is the no-JavaScript "older posts" path. Note that a query string
// makes Kirby treat the page as uncacheable, which is correct here: these are
// one-off deep links, not the hot path.
$cursor = $kirby->request()->get('cursor');
$feed   = $site->feed(is_string($cursor) ? $cursor : null);

?>
<?php snippet('header') ?>

<main id="swup" data-page-id="<?= esc($page->id(), 'attr') ?>" class="stream">

  <?php snippet('stream-feed/stream-feed', ['feed' => $feed]) ?>
</main>

<?php snippet('footer') ?>
