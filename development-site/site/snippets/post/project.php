<?php /** @var Kirby\Cms\Page $post */ ?>

<?php
$layers = $post->layers()->toStructure();
$layerCount = $layers->count();
$stackDepth = min(max($layerCount - 1, 0), 3);
?>

<div class="tile__project">
  <h2 class="tile__project-title"><?= esc($post->title()->value()) ?></h2>

  <div
    class="tile__project-stack"
    style="--project-layer-count: <?= esc((string) $layerCount, 'attr') ?>; --project-stack-depth: <?= esc((string) $stackDepth, 'attr') ?>">
    <?php foreach ($layers as $index => $layer): ?>
      <?php $depth = min($index, $stackDepth); ?>
      <div
        class="tile__project-layer"
        style="--project-depth: <?= esc((string) $depth, 'attr') ?>; --project-index: <?= esc((string) $index, 'attr') ?>">
        <?php if ($layer->type()->value() === 'text'): ?>
          <div class="tile__project-text"><?= $layer->text()->kt() ?></div>
        <?php else: ?>
          <?php
            $file = $layer->file()->toFile();
            if ($file !== null && $file->template() !== 'project-image-file') {
              $file = null;
            }

            if ($file):
              $img = $file->lazyImg([
                  'width' => 1200,
                  'srcset' => option('risto.stream.tileWidths', [400, 800, 1200, 1600]),
              ]);
          ?>
            <figure class="tile__media">
              <?php snippet('lazy-img/lazy-img', [
                  'img' => $img,
                  'sizes' => '(min-width: 60rem) 33vw, 100vw',
                  'alt' => $file->alt()->value(),
              ]) ?>
            </figure>
          <?php endif ?>
        <?php endif ?>
      </div>
    <?php endforeach ?>
  </div>
</div>
