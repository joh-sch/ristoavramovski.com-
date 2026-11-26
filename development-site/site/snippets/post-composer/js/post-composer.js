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
      projectField: null,
      projectTitle: null,
      projectLayers: null,
      projectLayersInput: null,
      requestId: null,
      addTextLayer: null,
      addImageLayer: null,
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
    this.onAddTextLayer = this.handleAddTextLayer.bind(this);
    this.onAddImageLayer = this.handleAddImageLayer.bind(this);
  }

  ////////////// Mount ///////////////
  ////////////////////////////////////

  mount() {
    if (!this.ref.dialog || typeof this.ref.dialog.showModal !== 'function') return;

    this.ref.add.addEventListener('click', this.onAddClick);
    this.ref.form.addEventListener('submit', this.onSubmit);
    this.ref.cancel.addEventListener('click', this.onCancel);
    this.ref.type.forEach((input) => input.addEventListener('change', this.onTypeChange));
    this.ref.addTextLayer.addEventListener('click', this.onAddTextLayer);
    this.ref.addImageLayer.addEventListener('click', this.onAddImageLayer);

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
    this.ref.addTextLayer.removeEventListener('click', this.onAddTextLayer);
    this.ref.addImageLayer.removeEventListener('click', this.onAddImageLayer);
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

  handleAddTextLayer() {
    this.addProjectLayer('text');
  }

  handleAddImageLayer() {
    this.addProjectLayer('image');
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

    if (type === 'project') {
      if (!this.ref.projectTitle.value.trim()) {
        return this.showError('Please give the project a title.');
      }

      const project = this.readProjectLayers();

      if (!project.layers.length) {
        return this.showError('Add at least one text or image layer.');
      }

      if (project.error) {
        return this.showError(project.error);
      }

      this.ref.projectLayersInput.value = JSON.stringify(project.layers);
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

      // Deliberately a reload rather than prepending the new tile. Inserting
      // at the head of a masonry layout shifts every tile below it, so it
      // costs a full relayout anyway — and a reload keeps the server as the
      // single authority on how a tile renders. Posting is infrequent and
      // lands you at the top of the feed, which is where the new post is.
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
    this.ref.projectField.hidden = type !== 'project';

    this.ref.textLabel.textContent = {
      text: 'Text',
      image: 'Caption (optional)',
      link: 'Note (optional)',
      project: 'Project summary (optional)',
    }[type];

    // Only the text post requires the textarea, so the browser's own
    // validation stays accurate as the type changes.
    this.ref.text.required = type === 'text';
    this.ref.url.required = type === 'link';
    this.ref.image.required = type === 'image';
    this.ref.projectTitle.required = type === 'project';

    if (type === 'project' && !this.ref.requestId.value) {
      this.ref.requestId.value = globalThis.crypto?.randomUUID
        ? globalThis.crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    if (type === 'project' && this.ref.projectLayers.children.length === 0) {
      this.addProjectLayer('text');
    }
  }

  addProjectLayer(type) {
    if (this.ref.projectLayers.children.length >= 12) {
      this.showError('Projects can contain up to 12 layers.');
      return;
    }

    const index = this.ref.projectLayers.children.length;
    const row = document.createElement('div');
    row.className = 'composer__project-layer';
    row.dataset.type = type;

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.textContent = 'Remove';
    remove.addEventListener('click', () => row.remove());

    if (type === 'text') {
      const label = document.createElement('label');
      label.className = 'composer__label';
      label.textContent = 'Text';

      const textarea = document.createElement('textarea');
      textarea.rows = 4;
      textarea.maxLength = 5000;
      textarea.dataset.projectField = 'text';

      row.append(label, textarea, remove);
    } else {
      const imageLabel = document.createElement('label');
      imageLabel.className = 'composer__label';
      imageLabel.textContent = 'Image';

      const image = document.createElement('input');
      image.type = 'file';
      image.name = `project-image-${index}`;
      image.accept = '.jpg,.jpeg,.png,.gif,.webp,.avif,.heic,.heif';
      image.dataset.projectField = 'image';

      const altLabel = document.createElement('label');
      altLabel.className = 'composer__label';
      altLabel.textContent = 'Alt text';

      const alt = document.createElement('input');
      alt.type = 'text';
      alt.dataset.projectField = 'alt';

      row.append(imageLabel, image, altLabel, alt, remove);
    }

    this.ref.projectLayers.append(row);
  }

  readProjectLayers() {
    const rows = Array.from(this.ref.projectLayers.children);
    const layers = [];

    if (rows.length === 0) {
      return { layers, error: null };
    }

    for (let index = 0; index < rows.length; index++) {
      const row = rows[index];
      const type = row.dataset.type;

      if (type === 'text') {
        const textarea = row.querySelector('[data-project-field="text"]');
        const text = textarea.value.trim();

        if (!text) return { layers, error: 'A text layer cannot be empty.' };
        if (text.length > 5000) return { layers, error: 'A text layer is too long.' };

        layers.push({ type: 'text', text });
      } else {
        const image = row.querySelector('[data-project-field="image"]');
        const alt = row.querySelector('[data-project-field="alt"]').value.trim();
        const file = image.files[0];

        if (!file) return { layers, error: 'Choose an image for every image layer.' };
        if (!alt) return { layers, error: 'Add alt text to every project image.' };
        if (file.size > this.options.maxSize) {
          return { layers, error: `That image is larger than ${Math.round(this.options.maxSize / 1048576)} MB.` };
        }

        image.name = `project-image-${index}`;
        layers.push({ type: 'image', alt });
      }
    }

    return { layers, error: null };
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
