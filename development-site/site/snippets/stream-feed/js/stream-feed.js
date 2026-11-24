////// Lib. //////
//////////////////

import Component from 'gia/Component';
import { refreshLazyLoad } from '../../../../app/modules/LazyLoad.js';

///// Assets /////
//////////////////

// Column count by container width. Tiles never get narrower than roughly
// 18rem, which is about where a text tile stops being readable.
const BREAKPOINTS = [
  { minWidth: 0, columns: 1 },
  { minWidth: 560, columns: 2 },
  { minWidth: 880, columns: 3 },
  { minWidth: 1200, columns: 4 },
];

const GAP = 16;

// Comp. class ///
//////////////////

/**
 * Column masonry plus cursor-driven infinite scroll.
 *
 * ── Why this is not CSS ─────────────────────────────────────────────────
 * `columns` fills column 1 before column 2, so an endless feed would run the
 * first column infinitely long and read vertically instead of chronologically.
 * Grid row-spans need `dense` to avoid gaps, and `dense` backfills a NEW post
 * into an OLD gap higher up the page — putting new work above old. Neither
 * gives shortest-column packing with chronological order.
 *
 * ── Why this is not slow ────────────────────────────────────────────────
 * Masonry is usually slow because it waits for images to load before it can
 * measure them. Here the server already knows every image's dimensions, and
 * lazy-img puts them on the <img> as an aspect-ratio — so the browser
 * reserves the right space immediately and a measurement taken before any
 * image has loaded is already correct.
 *
 * Measuring is batched: all widths are written, then all heights read, then
 * all positions written. One reflow per batch rather than one per tile.
 *
 * Column heights are carried forward, so appending a page positions only the
 * new tiles — existing ones are never touched.
 */
export default class StreamFeed extends Component {
  /////////// Constructor ////////////
  ////////////////////////////////////

  constructor(el) {
    super(el);

    //// DOM references ////
    ////////////////////////

    this.ref = {
      grid: null,
      sentinel: null,
      more: null,
      status: null,
    };

    /////// Options ////////
    ////////////////////////

    // Setter: merges g-options over these. See CLAUDE.md.
    this.options = {
      name: 'StreamFeed',
      feedUrl: '',
      cursor: null,
      logs: false,
    };

    /////// State //////////
    ////////////////////////

    this.columns = 0;
    this.columnHeights = [];
    this.columnWidth = 0;
    this.loading = false;
    this.exhausted = false;
    this.observer = null;
    this.resizeObserver = null;

    this.onResize = this.handleResize.bind(this);
  }

  ////////////// Mount ///////////////
  ////////////////////////////////////

  mount() {
    if (!this.ref.grid) return;

    this.element.classList.add('is-masonry');
    this.layoutAll();

    // Observing the grid rather than the window catches sidebars and zoom,
    // not just viewport changes.
    if ('ResizeObserver' in window) {
      this.resizeObserver = new ResizeObserver(this.onResize);
      this.resizeObserver.observe(this.ref.grid);
    } else {
      window.addEventListener('resize', this.onResize);
    }

    this.observeSentinel();
  }

  ///////////// Unmount //////////////
  ////////////////////////////////////

  unmount() {
    this.resizeObserver?.disconnect();
    this.observer?.disconnect();
    window.removeEventListener('resize', this.onResize);
  }

  ////////////// Layout //////////////
  ////////////////////////////////////

  /**
   * Re-lays out every tile. Only on first mount and on a column-count change.
   */
  layoutAll() {
    const width = this.ref.grid.clientWidth;
    if (width === 0) return;

    this.columns = BREAKPOINTS.reduce((n, bp) => (width >= bp.minWidth ? bp.columns : n), 1);
    this.columnWidth = (width - GAP * (this.columns - 1)) / this.columns;
    this.columnHeights = new Array(this.columns).fill(0);

    this.place(Array.from(this.ref.grid.children));
  }

  /**
   * Positions the given tiles, continuing from the current column heights.
   */
  place(tiles) {
    // Pass 1 — write every width. Span is known from the markup, so no
    // measurement is needed to decide it.
    const items = tiles.map((tile) => {
      const span = Math.min(parseInt(tile.dataset.span || '1', 10) || 1, this.columns);
      const width = this.columnWidth * span + GAP * (span - 1);

      tile.style.width = `${width}px`;

      // A tile being re-laid out still carries content-visibility from its
      // last placement, which would make an off-screen tile report its
      // contain-intrinsic-size instead of its real height.
      tile.classList.remove('is-placed');

      return { tile, span, width };
    });

    // Pass 2 — read every height. One reflow for the whole batch.
    for (const item of items) {
      item.height = item.tile.offsetHeight;
    }

    // Pass 3 — pack and write positions. Height does not depend on placement,
    // so this needs no further reads.
    for (const { tile, span, width, height } of items) {
      const column = this.shortestColumn(span);
      const top = Math.max(...this.columnHeights.slice(column, column + span));

      tile.style.transform = `translate3d(${column * (this.columnWidth + GAP)}px, ${top}px, 0)`;

      for (let i = column; i < column + span; i++) {
        this.columnHeights[i] = top + height + GAP;
      }

      tile.style.containIntrinsicSize = `${Math.round(width)}px ${Math.round(height)}px`;
      tile.classList.add('is-placed');
    }

    this.ref.grid.style.height = `${Math.max(...this.columnHeights, 0)}px`;
  }

  /**
   * Index of the span-wide slot whose tallest column is lowest.
   */
  shortestColumn(span) {
    let best = 0;
    let bestHeight = Infinity;

    for (let i = 0; i <= this.columns - span; i++) {
      const height = Math.max(...this.columnHeights.slice(i, i + span));

      // Strictly less-than keeps ties going to the leftmost column, which
      // preserves left-to-right reading order among equal candidates.
      if (height < bestHeight) {
        bestHeight = height;
        best = i;
      }
    }

    return best;
  }

  handleResize() {
    const width = this.ref.grid.clientWidth;
    if (width === 0) return;

    const columns = BREAKPOINTS.reduce((n, bp) => (width >= bp.minWidth ? bp.columns : n), 1);

    // Width changes within a breakpoint still need the tiles re-measured,
    // but this is the cheap common case: nothing to do if nothing moved.
    if (columns === this.columns && Math.abs(width - this.lastWidth) < 1) return;

    this.lastWidth = width;
    this.layoutAll();
  }

  ////////// Infinite scroll /////////
  ////////////////////////////////////

  observeSentinel() {
    if (!this.ref.sentinel || !this.options.cursor) return;

    if (!('IntersectionObserver' in window)) return; // the link stays as the fallback

    this.observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) this.loadMore();
      },
      // Start fetching before the sentinel is actually visible.
      { rootMargin: '800px 0px' },
    );

    this.observer.observe(this.ref.sentinel);

    // The link is redundant once the observer is live.
    if (this.ref.more) this.ref.more.hidden = true;
  }

  async loadMore() {
    if (this.loading || this.exhausted || !this.options.cursor) return;

    this.loading = true;
    this.setStatus('Loading older posts…');

    try {
      const url = `${this.options.feedUrl}?cursor=${encodeURIComponent(this.options.cursor)}`;
      const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      if (!response.ok) throw new Error('bad response');

      const result = await response.json();

      this.append(result.html);
      this.options.cursor = result.nextCursor;

      if (!result.nextCursor) {
        this.exhausted = true;
        this.observer?.disconnect();
        if (this.ref.more) this.ref.more.hidden = true;
        this.setStatus(null);
      } else {
        this.setStatus(null);
      }
    } catch {
      // Put the link back so the feed stays navigable without the observer.
      this.exhausted = true;
      this.observer?.disconnect();
      if (this.ref.more) this.ref.more.hidden = false;
      this.setStatus('Could not load older posts.');
    } finally {
      this.loading = false;
    }
  }

  /**
   * Appends a batch and positions only the new tiles.
   */
  append(html) {
    if (!html) return;

    const fragment = document.createRange().createContextualFragment(html);
    const tiles = Array.from(fragment.children);

    this.ref.grid.appendChild(fragment);

    // Existing tiles keep their positions: packing continues from the stored
    // column heights rather than starting over.
    this.place(tiles);

    refreshLazyLoad();
  }

  ////////////// Helpers /////////////
  ////////////////////////////////////

  setStatus(message) {
    if (!this.ref.status) return;

    this.ref.status.textContent = message || '';
    this.ref.status.hidden = !message;
  }
}
