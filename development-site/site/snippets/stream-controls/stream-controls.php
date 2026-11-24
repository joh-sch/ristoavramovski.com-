<?php

/**
 * The + button and its dialogs.
 *
 * Two states:
 *   - logged out → + links to /login, upgraded by JS into a login dialog
 *   - logged in  → + opens the composer, plus a logout control
 *
 * Calling $kirby->user() here is load-bearing beyond the branch: it is what
 * marks the response as cookie-dependent, so Kirby serves cached HTML to
 * anonymous visitors and always re-renders for the artist. See docs/PLAN.md §1.1.
 *
 * The CSRF token is only embedded in the logged-in branch. That render is
 * private and never cached, so it is safe there — whereas embedding it in the
 * anonymous branch would start a session for every visitor and disable the
 * page cache outright. Logged-out, the token is fetched when the dialog opens.
 */

$user = $kirby->user();

?>

<div
  g-component="StreamControls"
  g-options  ='<?= json_encode((object) [
      'tokenUrl'  => url('_api/session/token'),
      'loginUrl'  => url('_api/session'),
      'authed'    => $user !== null,
  ]) ?>'
  class      ="stream-controls">

  <?php if ($user): ?>

    <?php // Owns its own + button; see snippets/post-composer/. ?>
    <?php snippet('post-composer/post-composer') ?>

    <form
      method="post"
      action="<?= url('_api/session/end') ?>"
      class="stream-controls__logout">
      <input type="hidden" name="csrf" value="<?= esc(csrf(), 'attr') ?>">
      <button type="submit" class="stream-controls__logout-button">
        Log out<?= $user->name()->isNotEmpty() ? ' (' . esc($user->name()) . ')' : '' ?>
      </button>
    </form>

  <?php else: ?>

    <?php
      // data-no-swup is required, not decorative: Swup's click handler is
      // delegated on document and does not check event.defaultPrevented, so
      // calling preventDefault() in the component is not enough to stop it
      // navigating to /login behind the open dialog.
    ?>
    <a
      href="<?= url('login') ?>"
      g-ref="add"
      data-no-swup
      class="stream-controls__add"
      aria-haspopup="dialog"
      aria-label="Log in to add a post">+</a>

    <dialog g-ref="dialog" class="stream-controls__dialog" aria-label="Log in">
      <form
        method="post"
        action="<?= url('_api/session') ?>"
        g-ref="form"
        class="stream-controls__form">

        <?php // Filled by JS on open; see the note at the top of this file. ?>
        <input type="hidden" name="csrf" g-ref="csrf" value="">

        <label class="stream-controls__label" for="stream-login-email">Email</label>
        <input
          type="email"
          id="stream-login-email"
          name="email"
          g-ref="email"
          autocomplete="username"
          required>

        <label class="stream-controls__label" for="stream-login-password">Password</label>
        <input
          type="password"
          id="stream-login-password"
          name="password"
          autocomplete="current-password"
          required>

        <p g-ref="error" class="stream-controls__error" role="alert" hidden></p>

        <div class="stream-controls__actions">
          <button type="button" g-ref="cancel" class="stream-controls__cancel">Cancel</button>
          <button type="submit" g-ref="submit">Log in</button>
        </div>
      </form>
    </dialog>

  <?php endif ?>

</div>
