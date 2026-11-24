<?php
/**
 * One feed tile.
 *
 * Dispatches on the post's template, so a new post type is a new snippet
 * rather than another branch here.
 *
 * data-span says how many columns the tile occupies; the layout measures the
 * rest. Image tiles do not need to advertise their ratio here — lazy-img
 * already puts the server-known dimensions on the <img> as an aspect-ratio,
 * so the browser reserves the correct space before the image loads and a
 * measurement taken straight away is already right.
 */
/** @var Kirby\Cms\Page $post */

$type = $post->intendedTemplate()->name();
$size = (string) $post->size()->or('m');

// Large tiles take two columns where there is room, which is what gives the
// feed its varied rhythm. The layout clamps this to the column count.
$span = $size === 'l' ? 2 : 1;

?>
<article
  class="tile tile--<?= esc(str_replace('post-', '', $type), 'attr') ?> tile--size-<?= esc($size, 'attr') ?>"
  data-span="<?= esc($span, 'attr') ?>"
  id="tile-<?= esc($post->num(), 'attr') ?>">
  <?php snippet('post/' . $type, ['post' => $post]) ?>
</article>
