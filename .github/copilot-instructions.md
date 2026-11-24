# Risto 2026 — Project Guide

Kirby 5 flat-file site. Frontend is Tailwind CSS 4 + a Rollup/Babel IIFE bundle;
JS components use [gia](https://github.com/giantcode/gia) and are mounted/unmounted
across [Swup](https://swup.js.org/) page transitions.

## Layout

```
development-site/        ← web root; point the local server here
  app/                   ← frontend source + generated assets
    app.js               ← bundle entry; create-comp registers components here
    app.css              ← Tailwind entry
    modules/             ← app-level JS (PageTransitions, LazyLoad)
  content/               ← flat-file content
  site/
    blueprints/          ← Panel config
    config/              ← config.php + per-hostname overrides
    plugins/             ← site-methods (lazyImg)
    snippets/            ← components: NAME/NAME.php + NAME/js/NAME.js
    templates/
  storage/               ← accounts, cache, sessions (gitignored, roots in index.php)
build-utils/             ← init-kirby, create-comp, updt-version
file-templates/          ← source templates used by create-comp
tools/release/           ← optional GitHub release automation
```

## Commands

| Command | Does |
|---|---|
| `npm run watch` | Tailwind + Rollup watchers (dev build, unminified, source maps) |
| `npm run build` | Production CSS + minified JS |
| `npm run lint` | ESLint over app/ and snippet JS |
| `npm run reload` | Livereload server (enable `risto.livereload` in the local config) |
| `npm run create-comp "Name"` | Scaffolds a snippet + gia component and registers it in `app.js` |
| `npm run init-kirby` | Installs Kirby via Composer, creates runtime dirs |
| `npm run updt-version <patch\|minor\|major\|x.y.z>` | Bumps `package.json` + `RISTO_2026_VERSION`; add `--commit` to commit |

## Conventions

- Single quotes in PHP and JS unless the string contains one.
- `<?= ... ?>` rather than `<?php echo ... ?>` in templates.
- Escape all output: `esc($value)`, `esc($value, 'attr')` inside attributes.
- Components live in `development-site/site/snippets/NAME/`, JS in `NAME/js/NAME.js`.
  Always scaffold with `npm run create-comp` — it wires up the `app.js` import
  and registration via the anchor comments. Do not hand-edit those anchors away.
- Conventional Commits: `type(scope): description`.

## Things that will bite you

- **Every Swup-managed template needs exactly one `id="swup"` container** with a
  `data-page-id` attribute. Its value is mirrored onto `body[data-page]` after
  each navigation (`app/modules/PageTransitions.js`).
- **`RISTO_2026_VERSION`** is defined in `site/config/config.php` and consumed by
  `snippets/header.php` and `snippets/footer.php` for cache busting. Renaming it
  means renaming it in all four places — `updt-version.js` knows about it too.
- **Runtime roots are custom.** `index.php` points `accounts`, `cache` and
  `sessions` at `development-site/storage/`, not the Kirby defaults under `site/`.
- **`composer.lock` is committed on purpose.** Don't gitignore it; it pins the
  exact Kirby patch version across environments.
- **gia is unmaintained** (last release 2022). `rollup.config.mjs` injects
  `regeneratorRuntime` into gia only — that shim is load-bearing, don't remove it.
- **ESLint stays on 9.x and Babel on 7.x.** `@rollup/plugin-eslint` peers on
  `eslint ^8.57 || ^9`, and `@rollup/plugin-babel@6` peers on `@babel/core ^7`.
  Upgrading either major breaks the build.
- **Never commit** generated assets, `vendor/`, `storage/`, `media/`, or
  `config.localhost.php`.
