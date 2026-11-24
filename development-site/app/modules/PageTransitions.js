import Swup from 'swup';
import loadComponents from 'gia/loadComponents';
import removeComponents from 'gia/removeComponents';
import { initLazyLoad, refreshLazyLoad } from './LazyLoad.js';

const SWUP_CONTAINER = '#swup';

let swupInstance = null;

const syncBodyPage = () => {
  const swupContainer = document.querySelector(SWUP_CONTAINER);
  const pageId = swupContainer?.dataset.pageId;

  if (pageId) document.body.dataset.page = pageId;
};

/**
 * Mounts gia components inside the Swup container.
 *
 * Deliberately not @swup/gia-plugin. That package imports gia/dist/*, where
 * loadComponents is a self-contained webpack bundle carrying its OWN copy of
 * gia's config module — so the plugin's `config.set('log', false)` writes to a
 * different instance than the one loadComponents reads, and every component
 * instantiation is logged to the console in production regardless.
 *
 * Importing gia/* directly means app.js's config applies, and it drops a
 * dependency whose last release was 2023.
 */
const mountComponents = (components) => {
  const container = document.querySelector(SWUP_CONTAINER);

  if (container) loadComponents(components, container);
};

const unmountComponents = () => {
  const container = document.querySelector(SWUP_CONTAINER);

  if (container) removeComponents(container);
};

export default function initPageTransitions(components = {}) {
  if (swupInstance) return swupInstance;
  if (!document.querySelector(SWUP_CONTAINER)) return null;

  swupInstance = new Swup({ containers: [SWUP_CONTAINER] });

  syncBodyPage();
  initLazyLoad();
  mountComponents(components);

  // Unmount before the container is swapped, so components can detach their
  // listeners while their elements still exist.
  swupInstance.hooks.before('content:replace', unmountComponents);

  swupInstance.hooks.on('content:replace', () => {
    syncBodyPage();
    mountComponents(components);
    refreshLazyLoad();
  });

  return swupInstance;
}
