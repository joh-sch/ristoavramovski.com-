////// Lib. //////
//////////////////

import Component from 'gia/Component';

// Comp. class ///
//////////////////

/**
 * Lets a visitor set the background and text colour.
 *
 * The values are written as --color-paper and --color-ink on <html>; every
 * other rule already derives from those, so nothing else participates.
 *
 * Reading and applying on mount would flash the default palette first, so the
 * stored values are applied by a tiny inline script in <head> instead. This
 * component only owns the UI and the writes.
 */
export default class ThemeControls extends Component {
  /////////// Constructor ////////////
  ////////////////////////////////////

  constructor(el) {
    super(el);

    //// DOM references ////
    ////////////////////////

    this.ref = {
      toggle: null,
      panel: null,
      paper: null,
      ink: null,
      reset: null,
    };

    /////// Options ////////
    ////////////////////////

    // Setter: merges g-options over these. See CLAUDE.md.
    this.options = {
      name: 'ThemeControls',
      storageKey: 'risto.theme',
      defaultPaper: '#f7f7f4',
      defaultInk: '#171717',
      logs: false,
    };

    this.onToggle = this.handleToggle.bind(this);
    this.onInput = this.handleInput.bind(this);
    this.onReset = this.handleReset.bind(this);
    this.onDocumentClick = this.handleDocumentClick.bind(this);
  }

  ////////////// Mount ///////////////
  ////////////////////////////////////

  mount() {
    if (!this.ref.panel) return;

    // Reflect whatever the inline script already applied, so the swatches
    // match the page rather than the defaults.
    const current = this.read();
    this.ref.paper.value = current.paper || this.options.defaultPaper;
    this.ref.ink.value = current.ink || this.options.defaultInk;

    this.ref.toggle.addEventListener('click', this.onToggle);
    this.ref.paper.addEventListener('input', this.onInput);
    this.ref.ink.addEventListener('input', this.onInput);
    this.ref.reset.addEventListener('click', this.onReset);
    document.addEventListener('click', this.onDocumentClick);
  }

  ///////////// Unmount //////////////
  ////////////////////////////////////

  unmount() {
    if (!this.ref.panel) return;

    this.ref.toggle.removeEventListener('click', this.onToggle);
    this.ref.paper.removeEventListener('input', this.onInput);
    this.ref.ink.removeEventListener('input', this.onInput);
    this.ref.reset.removeEventListener('click', this.onReset);
    document.removeEventListener('click', this.onDocumentClick);
  }

  ////////////// Handlers ////////////
  ////////////////////////////////////

  handleToggle() {
    this.setOpen(this.ref.panel.hidden);
  }

  handleDocumentClick(event) {
    if (!this.ref.panel.hidden && !this.element.contains(event.target)) this.setOpen(false);
  }

  handleInput() {
    this.apply(this.ref.paper.value, this.ref.ink.value);
    this.write({ paper: this.ref.paper.value, ink: this.ref.ink.value });
  }

  handleReset() {
    const root = document.documentElement;

    root.style.removeProperty('--color-paper');
    root.style.removeProperty('--color-ink');

    this.ref.paper.value = this.options.defaultPaper;
    this.ref.ink.value = this.options.defaultInk;

    try {
      window.localStorage.removeItem(this.options.storageKey);
    } catch {
      // Storage can be unavailable (private mode, blocked cookies). The
      // colours still reset for this page view, which is what was asked for.
    }
  }

  ////////////// Helpers /////////////
  ////////////////////////////////////

  setOpen(open) {
    this.ref.panel.hidden = !open;
    this.ref.toggle.setAttribute('aria-expanded', String(open));
  }

  apply(paper, ink) {
    const root = document.documentElement;

    root.style.setProperty('--color-paper', paper);
    root.style.setProperty('--color-ink', ink);
  }

  read() {
    try {
      return JSON.parse(window.localStorage.getItem(this.options.storageKey) || '{}') || {};
    } catch {
      return {};
    }
  }

  write(value) {
    try {
      window.localStorage.setItem(this.options.storageKey, JSON.stringify(value));
    } catch {
      // Not fatal — the change applies to this page view regardless.
    }
  }
}
