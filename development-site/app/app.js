import config from 'gia/config';
import loadComponents from 'gia/loadComponents';
import initPageTransitions from './modules/PageTransitions.js';

import StreamControls from '../site/snippets/stream-controls/js/stream-controls.js';
import PostComposer from '../site/snippets/post-composer/js/post-composer.js';
import StreamFeed from '../site/snippets/stream-feed/js/stream-feed.js';
import ThemeControls from '../site/snippets/theme-controls/js/theme-controls.js';
import DrawControls from '../site/snippets/draw-controls/js/draw-controls.js';
// create-comp inserts new imports here

const components = {
  'StreamControls': StreamControls,
  'PostComposer': PostComposer,
  'StreamFeed': StreamFeed,
  'ThemeControls': ThemeControls,
  'DrawControls': DrawControls,
  // create-comp inserts new registrations here
};

config.set('log', false);

document.addEventListener('DOMContentLoaded', () => {
  // Persistent chrome (the + button and its dialogs) sits outside #swup, so
  // SwupGiaPlugin never mounts it. Mount that subtree once, here.
  // loadComponents skips elements that already have an instance, and scoping
  // it to #chrome keeps it from warning about page components it has no
  // constructor for.
  const chrome = document.getElementById('chrome');
  if (chrome) loadComponents(components, chrome);

  // Everything inside #swup is mounted and unmounted per navigation.
  initPageTransitions(components);
});
