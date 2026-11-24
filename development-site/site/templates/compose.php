<?php

/**
 * No-JavaScript fallback for adding a post.
 *
 * Reuses the composer snippet so the two paths cannot drift apart: the same
 * fields are rendered here and inside the dialog on the front page. Passing
 * inline drops the <dialog> wrapper, which would otherwise be display:none
 * without an `open` attribute, and the + button, which is redundant here.
 */

if ($kirby->user() === null) {
    go(url('login'));
}

$errors = [
    'invalid'       => 'That post could not be saved — please check the fields.',
    'invalid-token' => 'Your session expired. Please try again.',
    'forbidden'     => 'Your account cannot add posts.',
    'failed'        => 'The post could not be saved.',
];

// Cast: a null array offset is a deprecation, which PHP 8.5 + Whoops turn
// into a 500 rather than a notice.
$errorMessage = $errors[(string) $kirby->request()->get('error')] ?? null;

?>
<?php snippet('header', ['showControls' => false]) ?>

<main id="swup" data-page-id="compose" class="page-shell transition-fade">
  <h1><?= esc($page->title()) ?></h1>

  <?php if ($errorMessage): ?>
    <p class="login-form__error" role="alert"><?= esc($errorMessage) ?></p>
  <?php endif ?>

  <div class="compose-page">
    <?php snippet('post-composer/post-composer', ['inline' => true]) ?>
  </div>

  <p><a href="<?= $site->homePage()->url() ?>">Back to the stream</a></p>
</main>

<?php snippet('footer') ?>
