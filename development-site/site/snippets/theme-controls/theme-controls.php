<?php

/**
 * Floating background / text colour control, bottom right.
 *
 * Writes --color-paper and --color-ink on <html>, which the whole stylesheet
 * already derives from, so nothing else needs to know this exists.
 *
 * Choices are per-visitor and live in localStorage — they are a viewing
 * preference, not site content, so they are deliberately not stored in Kirby
 * and not shared between people. See the early-apply script in header.php for
 * why they survive a page load without flashing.
 */

?>

<div
  g-component="ThemeControls"
  g-options  ='<?= json_encode((object) [
      'storageKey'   => 'risto.theme',
      'defaultPaper' => '#f7f7f4',
      'defaultInk'   => '#171717',
  ]) ?>'
  class      ="theme-controls">

  <button
    type="button"
    g-ref="toggle"
    class="theme-controls__toggle"
    aria-expanded="false"
    aria-controls="theme-panel"
    aria-label="Change colours">◐</button>

  <div g-ref="panel" id="theme-panel" class="theme-controls__panel" hidden>
    <label class="theme-controls__row">
      <span>Background</span>
      <input type="color" g-ref="paper" value="#f7f7f4">
    </label>

    <label class="theme-controls__row">
      <span>Text</span>
      <input type="color" g-ref="ink" value="#171717">
    </label>

    <button type="button" g-ref="reset" class="theme-controls__reset">Reset</button>
  </div>

</div>
