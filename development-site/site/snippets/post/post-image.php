<?php
/** @var Kirby\Cms\Page $post */
$file = $post->files()->template('post-image-file')->first();
if (!$file) return;

$img = $file->lazyImg([
    'width'  => 1200,
    'srcset' => option('risto.stream.tileWidths', [400, 800, 1200, 1600]),
]);
?>
<figure class="tile__media">
  <?php snippet('lazy-img/lazy-img', [
      'img'   => $img,
      'sizes' => '(min-width: 60rem) 33vw, 100vw',
      'alt'   => $file->alt()->value() ?: $post->caption()->value(),
  ]) ?>
  <?php if ($post->caption()->isNotEmpty()): ?>
    <figcaption class="tile__caption"><?= $post->caption()->kti() ?></figcaption>
  <?php endif ?>
</figure>
