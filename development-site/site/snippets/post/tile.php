<?php
/**
 * One feed tile.
 *
 * Dispatches on the post's template, so adding a post type later means adding
 * a snippet rather than editing a conditional here.
 *
 * content-visibility lets the browser skip rendering tiles that are off
 * screen, which is what keeps an endlessly growing feed cheap. It needs
 * contain-intrinsic-size to avoid scrollbar jitter; phase 4 sets that from
 * the real tile dimensions.
 */
/** @var Kirby\Cms\Page $post */
$type = $post->intendedTemplate()->name();
?>
<article class="tile tile--<?= esc(str_replace('post-', '', $type), 'attr') ?> tile--size-<?= esc($post->size()->or('m'), 'attr') ?>">
  <?php snippet('post/' . $type, ['post' => $post]) ?>
</article>
