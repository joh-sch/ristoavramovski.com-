<?php

$user = $kirby->user();

snippet('header');

?>
<main id="swup" data-page-id="<?= esc($page->id(), 'attr') ?>" class="project-detail">
  <article class="page-shell">
    <?php snippet('post/project', ['post' => $page]) ?>
  </article>
</main>

<?php snippet('footer') ?>
