<?php

/**
 * The feed: a column-masonry grid plus the sentinel that pulls in older posts.
 *
 * Renders as an ordinary flow without JavaScript — the masonry layout is an
 * enhancement, not a requirement, and the "Older posts" link below works on
 * its own.
 *
 * @var array{posts: Kirby\Cms\Pages, nextCursor: string|null} $feed
 */

$feed = $feed ?? ['posts' => new Kirby\Cms\Pages([]), 'nextCursor' => null];

?>

<div
  g-component="StreamFeed"
  g-options  ='<?= json_encode((object) [
      'feedUrl' => url('_api/feed'),
      'cursor'  => $feed['nextCursor'],
  ]) ?>'
  class      ="stream-feed">

  <div g-ref="grid" class="stream-feed__grid">
    <?php foreach ($feed['posts'] as $post): ?>
      <?php snippet('post/tile', ['post' => $post]) ?>
    <?php endforeach ?>
  </div>

  <?php if ($feed['posts']->isEmpty()): ?>
    <p class="stream-feed__empty">
      <?= kirby()->user() ? 'Nothing here yet — use + to add the first post.' : 'Nothing here yet.' ?>
    </p>
  <?php endif ?>

  <?php if ($feed['nextCursor'] !== null): ?>
    <div g-ref="sentinel" class="stream-feed__sentinel" aria-hidden="true"></div>

    <?php // The no-JS path, and a fallback if the observer never fires. ?>
    <p g-ref="more" class="stream-feed__more">
      <a href="?cursor=<?= esc($feed['nextCursor'], 'attr') ?>" data-no-swup>Older posts</a>
    </p>
  <?php endif ?>

  <p g-ref="status" class="stream-feed__status" role="status" aria-live="polite" hidden></p>

</div>
