<?php

/**
 * The stream.
 *
 * Calling $kirby->user() is load-bearing for caching, not just for the view:
 * it marks the response cookie-dependent, so anonymous visitors get cached
 * HTML and the artist always gets a fresh render. See docs/PLAN.md §1.1.
 *
 * The layout here is a plain flow. Column masonry, cursor-driven infinite
 * scroll and content-visibility arrive in phase 4; the tile snippets are
 * already shared with it.
 */

$user = $kirby->user();
$feed = $site->feed();

?>
<?php snippet('header') ?>

<main id="swup" data-page-id="<?= esc($page->id(), 'attr') ?>" class="stream">

  <?php if ($feed['posts']->isEmpty()): ?>
    <p class="stream__empty">
      <?= $user ? 'Nothing here yet — use + to add the first post.' : 'Nothing here yet.' ?>
    </p>
  <?php endif ?>

  <div class="stream__grid">
    <?php foreach ($feed['posts'] as $post): ?>
      <?php snippet('post/tile', ['post' => $post]) ?>
    <?php endforeach ?>
  </div>

  <?php if ($feed['nextCursor'] !== null): ?>
    <?php // Replaced by the IntersectionObserver sentinel in phase 4. ?>
    <p class="stream__more">
      <a href="?cursor=<?= esc($feed['nextCursor'], 'attr') ?>">Older posts</a>
    </p>
  <?php endif ?>

</main>

<?php snippet('footer') ?>
