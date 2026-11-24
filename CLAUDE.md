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
- **The stream's `num` discipline is load-bearing.** Posts are ordered and
  paginated by `num` (the folder number), which Kirby reads from the directory
  name without opening a file. Never sort or filter the whole post collection
  by a content field: benchmarked at 1,856 ms vs 56 ms for 20k posts.
  Cross-cutting queries belong in the feed index, not `->filterBy()`.
  See `docs/PLAN.md` §1.3 and `site/plugins/stream/src/Feed.php`.
- **`num` comes from the stored `sortkey` field, not from the date.** The
  blueprints deliberately use `num: "{{ page.sortkey }}"`. Writing
  `{{ page.date.toDate('YmdHis') }}` looks equivalent but silently breaks:
  this project sets `date.handler: intl`, where `toDate()` takes an ICU
  pattern and `m` means *minute*, yielding `2026452018` instead of
  `20261120184500`. `Risto\Stream\Stream::num()` is the single definition.
- **HEIC never reaches the thumb pipeline.** `Kirby\Image\Image::$resizableTypes`
  excludes `heic`/`heif`, so uploads are converted to AVIF on arrival. Requires
  the `imagick` PHP extension in production; this dev machine falls back to GD,
  which can write AVIF but cannot read HEIC.
- **gia components get options from `g-options`, not the constructor.**
  `this.options = {...}` is a setter on gia's `BaseComponent` that merges the
  element's `g-options` attribute over your defaults. The constructor's second
  argument is never passed by `loadComponents()` or `@swup/gia-plugin`, so
  reading it throws and the component silently never mounts.
- **Persistent chrome mounts separately from page content.** Anything outside
  `#swup` (the `+` button, dialogs) is invisible to `SwupGiaPlugin`. `app.js`
  mounts `#chrome` with `loadComponents(components, chrome)`; everything inside
  `#swup` is mounted and unmounted per navigation. Global UI goes in `#chrome`.
- **Swup ignores `event.defaultPrevented`.** Its click handler is delegated on
  `document`, so a component calling `preventDefault()` does not stop a
  navigation. Links that JS handles need `data-no-swup`.
- **Never put a CSRF token in a cacheable page.** `csrf()` starts a session,
  and a session cookie makes every later response private — measured to drop
  the home page from cached to uncached outright. The logged-out login form
  fetches its token from `/_api/session/token` when the dialog opens; only the
  logged-in render (already private) embeds one directly.
- **Login errors must come from `auth()->isBlocked()`, not the exception.**
  With `debug` off, `Auth::validatePassword()` discards the specific error and
  rethrows a generic `PermissionException`, so rate-limiting is undetectable
  from the caught exception in production.
- **Image capabilities are detected, never assumed.** `thumbs.format` comes
  from `Risto\Stream\Images::thumbFormat()`. Measured on this project: Homebrew
  PHP 8.4 CLI writes AVIF, MAMP PHP 8.5.2 (which serves the site) does not, and
  MAMP's bundled ImageMagick 7.1.0-23 has no heic/heif/avif delegates at all.
  Hardcoding `avif` breaks thumbnails on the machine this is developed on.
- **`num` must be unique per bucket.** The feed cursor advances with
  `num < last`, so two posts sharing a second make the feed skip one
  permanently. `Stream::uniqueNum()` probes the directory for a free number.
- **The post date field is pinned to second precision.** Kirby's date field
  defaults `time.step` to *5 minutes* and rounds the stored value. Since the
  update hook recomputes `sortkey` from the date, the default would move a
  post in the feed on its first Panel edit.
- **`$page->url()` shadows a content field named `url`.** Use
  `$page->content()->get('url')`. Same applies to any field colliding with a
  real Page method.
- **`$array[null]` is a 500 on PHP 8.5**, not a notice — Whoops escalates the
  deprecation. Cast request params before using them as array keys.
- **`$file->thumb()` does not write anything.** It returns a lazy
  `FileVersion`; `->save()` is what generates the file. Pre-generation without
  it is a no-op.
- **The masonry layout is JS, and deliberately so.** CSS `columns` fills
  column 1 before column 2 (an endless feed would run it infinitely long);
  Grid row-spans need `grid-auto-flow: dense`, which backfills *new* posts
  into *old* gaps — putting new work above old. Neither gives shortest-column
  packing in chronological order.
- **Never set `will-change` on feed tiles.** Transforms are written once and
  never animated, so it buys nothing, and promoting every tile in an endless
  feed to its own compositor layer costs memory.
- **Measure tiles before applying `content-visibility`.** A skipped element
  reports its `contain-intrinsic-size`, not its real height, so measuring a
  placed tile returns the previous estimate and corrupts the packing.
  `place()` strips `.is-placed`, measures, then re-adds it.
- **Tile heights are measured, not computed from aspect ratio.** An earlier
  attempt derived image heights arithmetically and lost the caption, which
  overlapped tiles by exactly the caption height. `lazy-img` already puts the
  server-known dimensions on the `<img>`, so the browser reserves correct
  space before load and a single batched measurement pass is exact.
- **Never commit** generated assets, `vendor/`, `storage/`, `media/`, or
  `config.localhost.php`.
