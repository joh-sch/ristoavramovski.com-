<?php snippet('header') ?>

<?php // Error content // ?>
<main id="swup" data-page-id="<?= esc($page->id(), 'attr') ?>" class="page-shell transition-fade">
  <h1><?= esc($page->title()) ?></h1>

  <div class="prose-content">
    <?= $page->text()->or('The requested page could not be found.')->kt() ?>
  </div>
</main>

<?php snippet('footer') ?>
