<?php

/**
 * The drawing tool, bottom left. Shown only to a logged-in artist.
 *
 * A sibling of the other chrome components, never nested inside one — gia
 * resolves refs across nested components and the outer one steals them.
 * See CLAUDE.md.
 *
 * This render is private (session cookie present) and never cached, so the
 * CSRF token is safe to embed directly.
 */

if (kirby()->user() === null) return;

?>

<div
  g-component="DrawControls"
  g-options  ='<?= json_encode((object) [
      'bundleUrl' => url('app/drawing.min.js?v=' . RISTO_2026_VERSION),
      'loadUrl'   => url('_api/drawing'),
      'saveUrl'   => url('_api/drawing'),
      'csrf'      => csrf(),
  ]) ?>'
  class      ="draw-controls">

  <button
    type="button"
    g-ref="toggle"
    class="draw-controls__toggle"
    aria-pressed="false"
    aria-label="Draw on the page">✎</button>

  <div g-ref="panel" class="draw-controls__panel" hidden>
    <label class="draw-controls__row">
      <span>Colour</span>
      <input type="color" g-ref="colour" value="#171717">
    </label>

    <label class="draw-controls__row">
      <span>Width</span>
      <input type="range" g-ref="width" min="1" max="40" value="4">
    </label>

    <div class="draw-controls__actions">
      <button type="button" g-ref="undo" class="draw-controls__button">Undo</button>
      <button type="button" g-ref="clear" class="draw-controls__button">Clear</button>
      <button type="button" g-ref="save" class="draw-controls__button">Save</button>
    </div>

    <p g-ref="status" class="draw-controls__status" role="status" aria-live="polite"></p>
  </div>

</div>
