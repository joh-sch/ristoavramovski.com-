<?php

/**
 * No-JavaScript login fallback, and where the + button points before JS
 * upgrades it into a dialog.
 *
 * Calling csrf() below marks the response private, so this page is never
 * written to the page cache — which is exactly what we want, and why it does
 * not need an explicit cache.ignore rule.
 */

if ($kirby->user() !== null) {
    go($site->homePage()->url());
}

$errors = [
    'invalid-credentials' => 'Those details were not recognised.',
    'missing-credentials' => 'Please enter an email address and password.',
    'invalid-token'       => 'Your session expired. Please try again.',
    'rate-limited'        => 'Too many attempts. Please wait a while before trying again.',
];

// Cast: a null array offset is a deprecation, which PHP 8.5 + Whoops turn
// into a 500 rather than a notice.
$errorMessage = $errors[(string) $kirby->request()->get('error')] ?? null;

?>
<?php snippet('header', ['showControls' => false]) ?>

<main id="swup" data-page-id="login" class="page-shell transition-fade">
  <h1><?= esc($page->title()) ?></h1>

  <form method="post" action="<?= url('_api/session') ?>" class="login-form">
    <input type="hidden" name="csrf" value="<?= esc(csrf(), 'attr') ?>">

    <?php if ($errorMessage): ?>
      <p class="login-form__error" role="alert"><?= esc($errorMessage) ?></p>
    <?php endif ?>

    <label for="login-email">Email</label>
    <input type="email" id="login-email" name="email" autocomplete="username" required autofocus>

    <label for="login-password">Password</label>
    <input type="password" id="login-password" name="password" autocomplete="current-password" required>

    <button type="submit">Log in</button>
  </form>
</main>

<?php snippet('footer') ?>
