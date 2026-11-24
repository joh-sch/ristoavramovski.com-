////// Lib. //////
//////////////////

import Component from 'gia/Component';

// Comp. class ///
//////////////////

/**
 * Progressive enhancement for the + button.
 *
 * Without JS the button is a plain link to /login and the form inside the
 * dialog posts normally — both paths hit the same endpoints. This class only
 * upgrades the interaction to an in-page dialog.
 */
export default class StreamControls extends Component {
  /////////// Constructor ////////////
  ////////////////////////////////////

  constructor(el) {
    super(el);

    //// DOM references ////
    ////////////////////////

    this.ref = {
      add: null,
      dialog: null,
      form: null,
      csrf: null,
      email: null,
      error: null,
      submit: null,
      cancel: null,
    };

    /////// Options ////////
    ////////////////////////

    // gia's BaseComponent defines `options` as a SETTER: assigning here reads
    // the element's g-options attribute and merges it over these defaults.
    // Do not read the constructor's second argument instead — it looks like
    // the options object but is never passed. Neither gia's loadComponents()
    // nor @swup/gia-plugin forwards it, so it is always undefined, and
    // touching a property on it throws before the component can mount.
    this.options = {
      name: 'StreamControls',
      tokenUrl: '',
      loginUrl: '',
      authed: false,
      logs: false,
    };

    // Bound so they can be removed again on unmount.
    this.onAddClick = this.handleAddClick.bind(this);
    this.onSubmit = this.handleSubmit.bind(this);
    this.onCancel = this.handleCancel.bind(this);
  }

  ////////////// Mount ///////////////
  ////////////////////////////////////

  mount() {
    // <dialog> is the whole reason this stays small: focus trapping, Esc to
    // close and the backdrop all come from the platform.
    if (!this.ref.dialog || typeof this.ref.dialog.showModal !== 'function') return;

    this.ref.add.addEventListener('click', this.onAddClick);
    this.ref.form.addEventListener('submit', this.onSubmit);
    this.ref.cancel.addEventListener('click', this.onCancel);
  }

  ///////////// Unmount //////////////
  ////////////////////////////////////

  unmount() {
    if (!this.ref.dialog) return;

    this.ref.add.removeEventListener('click', this.onAddClick);
    this.ref.form.removeEventListener('submit', this.onSubmit);
    this.ref.cancel.removeEventListener('click', this.onCancel);
  }

  ////////////// Handlers ////////////
  ////////////////////////////////////

  async handleAddClick(event) {
    event.preventDefault();

    this.showError(null);
    this.ref.dialog.showModal();
    this.ref.email.focus();

    // Fetched on open rather than rendered into the page: a token in the
    // markup would start a session for every anonymous visitor and disable
    // Kirby's page cache. See the snippet's header comment.
    await this.fetchToken();
  }

  handleCancel() {
    this.ref.dialog.close();
  }

  async handleSubmit(event) {
    event.preventDefault();

    this.setBusy(true);
    this.showError(null);

    try {
      const response = await fetch(this.options.loginUrl, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: new FormData(this.ref.form),
        credentials: 'same-origin',
      });

      const result = await response.json();

      if (!response.ok || result.ok !== true) {
        this.showError(result.message || 'Login failed.');

        // A rejected token is single-use spent — fetch a fresh one so the
        // next attempt is not guaranteed to fail too.
        if (result.error === 'invalid-token') await this.fetchToken();

        return;
      }

      // Reload rather than patching the DOM: the server render is what
      // decides which controls the artist gets, and it is the only place
      // that decision should live.
      window.location.reload();
    } catch {
      this.showError('Could not reach the server. Please try again.');
    } finally {
      this.setBusy(false);
    }
  }

  ////////////// Helpers /////////////
  ////////////////////////////////////

  async fetchToken() {
    try {
      const response = await fetch(this.options.tokenUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const { token } = await response.json();

      this.ref.csrf.value = token || '';
    } catch {
      // Leave the field empty: the server rejects the post with a readable
      // error rather than the form failing silently here.
      this.ref.csrf.value = '';
    }
  }

  setBusy(busy) {
    this.ref.submit.disabled = busy;
    this.ref.submit.textContent = busy ? 'Logging in…' : 'Log in';
  }

  showError(message) {
    this.ref.error.textContent = message || '';
    this.ref.error.hidden = !message;
  }
}
