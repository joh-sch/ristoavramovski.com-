// ESLint flat config (ESLint 9+)
// Lints all JS inside the app entry folder and component snippet folders.

import globals from 'globals';

const APP = 'development-site/app';
const SNIPPETS = 'development-site/site/snippets';

export default [
  {
    files: [`${APP}/**/*.js`, `${SNIPPETS}/**/*.js`],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        ...globals.browser,
      },
    },
    rules: {
      'no-unused-vars': 'warn',
      'no-undef': 'error',
    },
  },
  {
    // Generated bundles — never lint them.
    ignores: [`${APP}/*.min.js`],
  },
];
