<?php

/**
 * The + button and composer dialog, shown only to a logged-in artist.
 *
 * Owns its own + button rather than being opened by StreamControls: each
 * component then holds one complete state of the control, with no
 * cross-component messaging to keep in sync.
 *
 * Renders in two modes:
 *   default  — + button plus a <dialog>, upgraded by JS on the front page
 *   inline   — bare form, for /compose (a <dialog> without `open` is
 *              display:none, so the page would otherwise render blank)
 *
 * This render is always private (a session cookie is present), so it is never
 * written to the page cache and can embed a CSRF token directly.
 */

$inline     = ($inline ?? false) === true;
$maxSize    = (int) option('risto.stream.uploads.maxSize', 26214400);
$extensions = (array) option('risto.stream.uploads.extensions', []);
$canHeic    = Risto\Stream\Images::canDecode('heic');

// Offering .heic in the file picker on a server that cannot decode it would
// only produce a rejection after the upload has already completed.
$accept = array_values(array_filter(
    $extensions,
    fn (string $ext) => $canHeic === true || Risto\Stream\Images::needsConversion($ext) === false
));

?>

<div
  g-component="PostComposer"
  g-options  ='<?= json_encode((object) [
      'endpoint' => url('_api/posts'),
      'maxSize'  => $maxSize,
      'inline'   => $inline,
  ]) ?>'
  class      ="post-composer">

  <?php if ($inline === false): ?>
    <?php // A link, not a button: without JS this must still reach the form. ?>
    <a
      href="<?= url('compose') ?>"
      g-ref="add"
      data-no-swup
      class="stream-controls__add"
      aria-haspopup="dialog"
      aria-label="Add a post">+</a>

    <dialog g-ref="dialog" class="stream-controls__dialog" aria-label="Add a post">
  <?php endif ?>

    <form
      method="post"
      action="<?= url('_api/posts') ?>"
      enctype="multipart/form-data"
      g-ref="form"
      class="composer">

      <input type="hidden" name="csrf" value="<?= esc(csrf(), 'attr') ?>">

      <div class="composer__types" role="radiogroup" aria-label="Post type">
        <?php foreach (['text' => 'Text', 'image' => 'Image', 'link' => 'Link', 'project' => 'Project'] as $value => $label): ?>
          <label class="composer__type">
            <input
              type="radio"
              name="type"
              value="<?= esc($value, 'attr') ?>"
              g-ref="type"
              <?= $value === 'text' ? 'checked' : '' ?>>
            <?= esc($label) ?>
          </label>
        <?php endforeach ?>
      </div>

      <?php // Project ?>
      <div g-ref="projectField" class="composer__field" hidden>
        <label class="composer__label" for="composer-project-title">Project title</label>
        <input
          type="text"
          id="composer-project-title"
          name="projectTitle"
          g-ref="projectTitle"
          maxlength="120">

        <div g-ref="projectLayers" class="composer__project-layers" aria-label="Project layers"></div>
        <div class="stream-controls__actions">
          <button type="button" g-ref="addTextLayer">Add text</button>
          <button type="button" g-ref="addImageLayer">Add image</button>
        </div>
        <input type="hidden" name="projectLayers" g-ref="projectLayersInput">
        <input type="hidden" name="requestId" g-ref="requestId">
      </div>

      <?php // Text — also the caption for an image, and the note on a link. ?>
      <label class="composer__label" for="composer-text" g-ref="textLabel">Text</label>
      <textarea id="composer-text" name="text" g-ref="text" rows="4"></textarea>

      <?php // Link ?>
      <div g-ref="urlField" class="composer__field">
        <label class="composer__label" for="composer-url">URL</label>
        <input type="url" id="composer-url" name="url" g-ref="url" placeholder="https://">
      </div>

      <?php // Image ?>
      <div g-ref="imageField" class="composer__field">
        <label class="composer__label" for="composer-image">Image</label>
        <input
          type="file"
          id="composer-image"
          name="image"
          g-ref="image"
          accept="<?= esc(implode(',', array_map(fn ($e) => '.' . $e, $accept)), 'attr') ?>">
        <label class="composer__label" for="composer-alt">Alt text</label>
        <input type="text" id="composer-alt" name="alt" g-ref="alt">
      </div>

      <label class="composer__label" for="composer-size">Tile size</label>
      <select id="composer-size" name="size" g-ref="size">
        <option value="s">Small</option>
        <option value="m" selected>Medium</option>
        <option value="l">Large</option>
      </select>

      <p g-ref="error" class="stream-controls__error" role="alert" hidden></p>

      <div class="stream-controls__actions">
        <?php if ($inline === false): ?>
          <button type="button" g-ref="cancel" class="stream-controls__cancel">Cancel</button>
        <?php endif ?>
        <button type="submit" g-ref="submit">Post</button>
      </div>
    </form>

  <?php if ($inline === false): ?>
    </dialog>
  <?php endif ?>

</div>
