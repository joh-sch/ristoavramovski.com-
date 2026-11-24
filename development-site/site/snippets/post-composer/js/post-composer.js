////// Lib. //////
//////////////////

import Component from 'gia/Component';

// Comp. class ///
//////////////////

/**
 * The composer dialog.
 *
 * Without JS the + button links to /compose, which renders the same form as
 * an ordinary page. This only upgrades it to an in-page dialog and submits
 * via fetch so the feed can be updated without a full reload.
 */
export default class PostComposer extends Component {
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
      type: [],
      text: null,
      textLabel: null,
      url: null,
      urlField: null,
      image: null,
      imageField: null,
      alt: null,
      size: null,
      error: null,
      submit: null,
      cancel: null,
    };

    /////// Options ////////
    ////////////////////////

    // Setter: merges the element's g-options over these. The constructor's
    // second argument is never passed by gia — see CLAUDE.md.
    this.options = {
      name: 'PostComposer',
      endpoint: '',
      maxSize: 26214400,
      logs: false,
    };

    this.onAddClick = this.handleAddClick.bind(this);
    this.onSubmit = this.handleSubmit.bind(this);
    this.onCancel = this.handleCancel.bind(this);
    this.onTypeChange = this.handleTypeChange.bind(this);
  }

  ////////////// Mount ///////////////
  ////////////////////////////////////

  mount() {
    if (!this.ref.dialog || typeof this.ref.dialog.showModal !== 'function') return;

    this.ref.add.addEventListener('click', this.onAddClick);
    this.ref.form.addEventListener('submit', this.onSubmit);
    this.ref.cancel.addEventListener('click', this.onCancel);
    this.ref.type.forEach((input) => input.addEventListener('change', this.onTypeChange));

    this.applyType();
  }

  ///////////// Unmount //////////////
  ////////////////////////////////////

  unmount() {
    if (!this.ref.dialog) return;

    this.ref.add.removeEventListener('click', this.onAddClick);
    this.ref.form.removeEventListener('submit', this.onSubmit);
    this.ref.cancel.removeEventListener('click', this.onCancel);
    this.ref.type.forEach((input) => input.removeEventListener('change', this.onTypeChange));
  }

  ////////////// Handlers ////////////
  ////////////////////////////////////

  handleAddClick(event) {
    event.preventDefault();

    this.showError(null);
    this.ref.dialog.showModal();
    this.ref.text.focus();
  }

  handleCancel() {
    this.ref.dialog.close();
  }

  handleTypeChange() {
    this.applyType();
  }

  async handleSubmit(event) {
    event.preventDefault();

    const type = this.currentType();

    // Cheap client-side checks. The server repeats all of them — these only
    // save a round trip, they are not the enforcement point.
    if (type === 'image') {
      const file = this.ref.image.files[0];

      if (!file) return this.showError('Please choose an image.');
      if (file.size > this.options.maxSize) {
        return this.showError(`That image is larger than ${Math.round(this.options.maxSize / 1048576)} MB.`);
      }
    }

    if (type === 'text' && !this.ref.text.value.trim()) {
      return this.showError('Please write something first.');
    }

    if (type === 'link' && !this.ref.url.value.trim()) {
      return this.showError('Please enter a web address.');
    }

    this.setBusy(true);
    this.showError(null);

    try {
      const response = await fetch(this.options.endpoint, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: new FormData(this.ref.form),
        credentials: 'same-origin',
      });

      const result = await response.json();

      if (!response.ok || result.ok !== true) {
        this.showError(result.message || 'The post could not be saved.');
        return;
      }

      // Reloading keeps one source of truth for how a tile renders: the
      // server. Replaced in phase 4, where the new post is prepended to the
      // masonry layout in place.
      window.location.reload();
    } catch {
      this.showError('Could not reach the server. Please try again.');
    } finally {
      this.setBusy(false);
    }
  }

  ////////////// Helpers /////////////
  ////////////////////////////////////

  currentType() {
    const checked = this.ref.type.find((input) => input.checked);

    return checked ? checked.value : 'text';
  }

  applyType() {
    const type = this.currentType();

    this.ref.urlField.hidden = type !== 'link';
    this.ref.imageField.hidden = type !== 'image';

    this.ref.textLabel.textContent = {
      text: 'Text',
      image: 'Caption (optional)',
      link: 'Note (optional)',
    }[type];

    // Only the text post requires the textarea, so the browser's own
    // validation stays accurate as the type changes.
    this.ref.text.required = type === 'text';
    this.ref.url.required = type === 'link';
    this.ref.image.required = type === 'image';
  }

  setBusy(busy) {
    this.ref.submit.disabled = busy;
    this.ref.submit.textContent = busy ? 'Posting…' : 'Post';
  }

  showError(message) {
    this.ref.error.textContent = message || '';
    this.ref.error.hidden = !message;
  }
}
