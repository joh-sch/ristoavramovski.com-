<?php

$metaTitle       = $page->isHomePage() ? $site->title() : $page->title() . ' | ' . $site->title();
$metaDescription = $page->description()->or($site->description());

?>
<!doctype html>
<html lang="<?= $kirby->language()?->code() ?? 'en' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <?php // Meta // ?>
  <title><?= esc($metaTitle) ?></title>
  <?php if ($metaDescription->isNotEmpty()): ?>
    <meta name="description" content="<?= esc($metaDescription, 'attr') ?>">
  <?php endif ?>
  <link rel="canonical" href="<?= $page->url() ?>">

  <?php // Open Graph // ?>
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= esc($metaTitle, 'attr') ?>">
  <meta property="og:url" content="<?= $page->url() ?>">
  <?php if ($metaDescription->isNotEmpty()): ?>
    <meta property="og:description" content="<?= esc($metaDescription, 'attr') ?>">
  <?php endif ?>

  <?php
    // Favicons — drop the assets into app/ and uncomment:
    // echo '<link rel="icon" href="' . url('app/favicon.svg') . '" type="image/svg+xml">';
  ?>

  <?php // Styles // ?>
  <?= css('app/styles/styles.css?v=' . RISTO_2026_VERSION) ?>
</head>
<body data-page="<?= esc($page->id(), 'attr') ?>">

  <?php
    // Persistent chrome: everything here lives OUTSIDE #swup, so it survives
    // page transitions — and so SwupGiaPlugin never sees it. app.js mounts
    // this subtree separately. Global components belong in here.
  ?>
  <div id="chrome">
    <?php if (($showControls ?? true) === true) snippet('stream-controls/stream-controls') ?>
  </div>
