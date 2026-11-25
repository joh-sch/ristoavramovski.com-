////// Lib. //////
//////////////////

import Component from 'gia/Component';

///// Assets /////
//////////////////

/**
 * Loads a classic script once and resolves when it is ready.
 *
 * The editor is a separate IIFE bundle rather than a dynamic import, because
 * the app is built as a single IIFE — code splitting would mean switching the
 * whole output to ES modules for one rarely-used feature.
 */
function loadScript(src) {
  if (window.RistoDrawing) return Promise.resolve(window.RistoDrawing);

  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[data-drawing]`);

    if (existing) {
      existing.addEventListener('load', () => resolve(window.RistoDrawing));
      existing.addEventListener('error', reject);
      return;
    }

    const el = document.createElement('script');
    el.src = src;
    el.defer = true;
    el.dataset.drawing = '';
    el.addEventListener('load', () => resolve(window.RistoDrawing));
    el.addEventListener('error', () => reject(new Error('bundle failed to load')));
    document.head.appendChild(el);
  });
}

// Comp. class ///
//////////////////

/**
 * The pencil tool.
 *
 * Owns the button and the options panel; the heavy lifting lives in the
 * drawing bundle, which is fetched the first time the tool is opened.
 */
export default class DrawControls extends Component {
  /////////// Constructor ////////////
  ////////////////////////////////////

  constructor(el) {
    super(el);

    //// DOM references ////
    ////////////////////////

    this.ref = {
      toggle: null,
      panel: null,
      colour: null,
      width: null,
      undo: null,
      clear: null,
      save: null,
      status: null,
      busy: null,
    };

    /////// Options ////////
    ////////////////////////

    // Setter: merges g-options over these. See CLAUDE.md.
    this.options = {
      name: 'DrawControls',
      bundleUrl: '',
      loadUrl: '',
      saveUrl: '',
      csrf: '',
      logs: false,
    };

    this.drawing = null;
    this.active = false;
    this.dirty = false;
    this.saving = false;

    this.onToggle = this.handleToggle.bind(this);
    this.onBrush = this.handleBrush.bind(this);
    this.onUndo = this.handleUndo.bind(this);
    this.onClear = this.handleClear.bind(this);
    this.onSave = this.handleSave.bind(this);
    this.onKey = this.handleKey.bind(this);
    this.onBeforeUnload = this.handleBeforeUnload.bind(this);
  }

  ////////////// Mount ///////////////
  ////////////////////////////////////

  mount() {
    if (!this.ref.toggle) return;

    this.ref.toggle.addEventListener('click', this.onToggle);
    this.ref.colour.addEventListener('input', this.onBrush);
    this.ref.width.addEventListener('input', this.onBrush);
    this.ref.undo.addEventListener('click', this.onUndo);
    this.ref.clear.addEventListener('click', this.onClear);
    this.ref.save.addEventListener('click', this.onSave);
    document.addEventListener('keydown', this.onKey);
    window.addEventListener('beforeunload', this.onBeforeUnload);
  }

  ///////////// Unmount //////////////
  ////////////////////////////////////

  unmount() {
    if (!this.ref.toggle) return;

    this.ref.toggle.removeEventListener('click', this.onToggle);
    this.ref.colour.removeEventListener('input', this.onBrush);
    this.ref.width.removeEventListener('input', this.onBrush);
    this.ref.undo.removeEventListener('click', this.onUndo);
    this.ref.clear.removeEventListener('click', this.onClear);
    this.ref.save.removeEventListener('click', this.onSave);
    document.removeEventListener('keydown', this.onKey);
    window.removeEventListener('beforeunload', this.onBeforeUnload);
  }

  ////////////// Handlers ////////////
  ////////////////////////////////////

  async handleToggle() {
    if (this.active) return this.deactivate();

    await this.activate();
  }

  handleBrush() {
    this.drawing?.setBrush({
      colour: this.ref.colour.value,
      width: Number(this.ref.width.value),
    });
  }

  handleUndo() {
    this.drawing?.undo();
    this.dirty = true;
  }

  handleClear() {
    this.drawing?.clear();
    this.dirty = true;
  }

  handleKey(event) {
    if (!this.active) return;

    if (event.key === 'Escape') this.deactivate();

    if ((event.metaKey || event.ctrlKey) && event.key === 'z') {
      event.preventDefault();
      this.handleUndo();
    }
  }

  handleBeforeUnload(event) {
    // Drawings live on the server, so leaving mid-edit loses them.
    if (!this.active || !this.dirty) return;

    event.preventDefault();
    event.returnValue = '';
  }

  async handleSave() {
    if (!this.drawing || this.saving) return;

    const payload = this.drawing.serialise();

    if (!payload) return;

    this.setBusy(true);

    try {
      const body = new FormData();
      body.set('csrf', this.options.csrf);
      body.set('json', payload.json);
      body.set('svg', payload.svg);
      body.set('width', String(payload.width));
      body.set('height', String(payload.height));

      const response = await fetch(this.options.saveUrl, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        body,
      });

      const result = await response.json();

      if (!response.ok || result.ok !== true) {
        this.setStatus(result.message || 'Could not save.');
        return;
      }

      // The server hands back the sanitised markup; swapping that in keeps
      // the page showing exactly what was stored, with no reload.
      this.updateLayer(result);

      this.dirty = false;
      this.setStatus('Saved');
    } catch {
      this.setStatus('Could not reach the server.');
    } finally {
      this.setBusy(false);
    }
  }

  /**
   * Replaces the static SVG layer with what the server stored.
   *
   * Hidden while the editor is open — the canvas already shows the same
   * strokes, and two copies at identical coordinates would double up.
   */
  updateLayer({ svg, width, height }) {
    const existing = document.querySelector('.drawing-layer');

    if (!svg || !width || !height) {
      existing?.remove();
      return;
    }

    const layer = existing || document.createElement('div');

    if (!existing) {
      layer.className = 'drawing-layer';
      layer.setAttribute('aria-hidden', 'true');
      document.body.appendChild(layer);
    }

    layer.style.setProperty('--drawing-ar', `${width} / ${height}`);
    layer.innerHTML = svg;
  }

  ////////////// Modes ///////////////
  ////////////////////////////////////

  async activate() {
    this.setStatus('Loading…');

    try {
      this.drawing = await loadScript(this.options.bundleUrl);
    } catch {
      this.setStatus('Drawing tools failed to load.');
      return;
    }

    let existing = null;

    try {
      const response = await fetch(this.options.loadUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const data = await response.json();
      existing = data.json ? JSON.parse(data.json) : null;
    } catch {
      // Start from a blank layer rather than refusing to open.
    }

    await this.drawing.open({
      colour: this.ref.colour.value,
      width: Number(this.ref.width.value),
      existing,
    });

    this.active = true;
    this.ref.panel.hidden = false;
    this.ref.toggle.setAttribute('aria-pressed', 'true');
    document.documentElement.classList.add('is-drawing');
    this.setStatus(null);
  }

  deactivate() {
    this.drawing?.close();
    this.active = false;
    this.dirty = false;
    this.ref.panel.hidden = true;
    this.ref.toggle.setAttribute('aria-pressed', 'false');
    document.documentElement.classList.remove('is-drawing');
    this.setStatus(null);
  }

  ////////////// Helpers /////////////
  ////////////////////////////////////

  /**
   * Locks the page while a save is in flight.
   *
   * aria-busy and inert together: inert removes everything from the tab order
   * and blocks pointer events, which is what actually makes the page
   * non-interactive, while aria-busy tells assistive tech why.
   */
  setBusy(busy) {
    this.saving = busy;
    this.ref.busy.hidden = !busy;
    this.ref.save.disabled = busy;
    this.ref.undo.disabled = busy;
    this.ref.clear.disabled = busy;

    document.documentElement.classList.toggle('is-saving', busy);
    document.body.setAttribute('aria-busy', String(busy));

    if (busy) this.setStatus(null);
  }

  setStatus(message) {
    this.ref.status.textContent = message || '';
  }
}
