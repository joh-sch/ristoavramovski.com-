<?php

/**
 * The saved drawing, inlined as SVG.
 *
 * Deliberately not rendered by Fabric: that would make every visitor download
 * ~292KB of editor to look at a few strokes. The SVG is produced once, when
 * the artist saves, and served flat.
 *
 * The viewBox is the size the drawing was authored at, so it scales with the
 * page rather than being pinned to the authoring viewport.
 */

$drawing = Risto\Stream\Drawing::read();

if (empty($drawing['svg']) || $drawing['width'] < 1) return;

?>
<div
  class="drawing-layer"
  aria-hidden="true"
  style="--drawing-ar: <?= esc($drawing['width'], 'attr') ?> / <?= esc($drawing['height'], 'attr') ?>">
  <?php
    // Already sanitised on write (Drawing::sanitiseSvg). Escaping here would
    // print the markup instead of rendering it.
    echo $drawing['svg'];
  ?>
</div>
