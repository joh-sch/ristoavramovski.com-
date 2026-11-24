<?php
/** @var Kirby\Cms\Page $post */

// content()->get('url'), not $post->url() — Page::url() is a real method, so
// the magic field accessor is shadowed and would return the page's own URL.
$url  = $post->content()->get('url')->value();
$host = (string) parse_url((string) $url, PHP_URL_HOST);
?>
<div class="tile__link">
  <?php if ($post->text()->isNotEmpty()): ?>
    <p class="tile__note"><?= $post->text()->kti() ?></p>
  <?php endif ?>
  <?php // rel protects against tabnabbing on an artist-supplied destination ?>
  <a href="<?= esc($url, 'attr') ?>" rel="noopener noreferrer nofollow ugc" target="_blank">
    <?= esc($host ?: $url) ?>
  </a>
</div>
