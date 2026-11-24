# Risto 2026

Kirby 5 website. Tailwind CSS 4, Rollup, gia component lifecycles, Swup page
transitions, lazy-loaded responsive images.

Derived from the `barebone-kirby-2026` template.

## Requirements

- PHP 8.2–8.5 (tested on 8.4)
- Composer
- Node.js 20+ and npm
- A local web server whose document root points at `development-site/`

## Setup

```sh
npm install
npm --prefix build-utils install
npm run init-kirby
cp development-site/site/config/config.localhost.php.example \
   development-site/site/config/config.localhost.php
npm run build
```

Then point the local server (MAMP/Valet/Herd) at `development-site/`, open
`/panel`, create the first admin account, and set `panel.install` back to
`false` in `config.localhost.php`.

If your local hostname is not `localhost`, rename the local config to
`config.{your-hostname}.php` — Kirby loads it by request hostname, without port.

## Development

- `npm run watch` — Tailwind + Rollup watchers. Dev builds are unminified with
  source maps; lint errors warn but do not kill the watcher.
- `npm run build` — production CSS and minified JS. Lint errors fail the build.
- `npm run lint` — ESLint on its own.
- `npm run reload` — livereload server. Enable `risto.livereload` in the local
  config to have the client script injected.
- `npm run create-comp "Feature Name"` — scaffolds
  `site/snippets/feature-name/feature-name.php` plus
  `site/snippets/feature-name/js/feature-name.js`, and registers the component
  in `development-site/app/app.js`.

Every Swup-managed template must contain exactly one container with `id="swup"`.
Its `data-page-id` value is synchronised to `body[data-page]` after navigation.
Swup intercepts eligible same-origin links automatically — there is no
navigation markup in the template.

## Lazy Images

```php
<?php snippet('lazy-img/lazy-img', [
    'img' => $page->image()->lazyImg([
        'width'  => 1500,
        'srcset' => [480, 960, 1500],
    ]),
    'sizes' => '100vw',
]) ?>
```

The `lazyImg` file method returns a constrained thumbnail, an optional `srcset`,
a tiny blurred placeholder, and intrinsic dimensions. The snippet emits
`width`/`height` to reserve layout space and a `<noscript>` fallback. Widths are
capped at 1500px.

## Versioning

`RISTO_2026_VERSION` in `site/config/config.php` is the cache-busting token for
CSS and JS. Bump it together with `package.json`:

```sh
npm run updt-version patch           # update files only
npm run updt-version patch --commit  # update, stage and commit
```

## Structure

- `development-site/` — web root (app sources, content, site, runtime state)
- `build-utils/` — Kirby installation, component scaffolding, versioning
- `file-templates/` — source templates consumed by `create-comp`
- `tools/release/` — optional GitHub release automation (`npm run release`)

## Not In The Repo

Regenerated or local-only, all gitignored:

- `node_modules/`, `build-utils/node_modules/`, `development-site/vendor/`
- `development-site/app/app.min.js*`, `development-site/app/styles/styles.css`
- `development-site/media/`, `development-site/storage/` (accounts, cache, sessions)
- `development-site/site/config/config.localhost.php`

`composer.lock` **is** committed — it pins the exact Kirby version for every
environment.
