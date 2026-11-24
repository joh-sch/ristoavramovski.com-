# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.0.0] — 2026-11-24

### Added

- Initial project setup, derived from `barebone-kirby-2026`.
- Kirby `.htaccess` with rewrite rules and protection for `content/`, `site/`, `storage/`, `kirby/` and `vendor/`.
- Custom Kirby roots in `index.php` pointing accounts, cache and sessions at `development-site/storage/`.
- Meta description, canonical URL and Open Graph tags in the header snippet.
- Site title and meta description fields in the Panel.
- `robots.txt` and `.editorconfig`.
- `CLAUDE.md` project guide.

### Changed

- Pinned Kirby to `^5.6`; `composer.lock` is now tracked.
- Terser runs in production builds only; development builds keep readable output and source maps.
- ESLint now uses the `globals` package instead of a hand-maintained globals list; `no-undef` raised to error.
- Browser targets moved to a single `browserslist` key in `package.json`.
- `lazyImg` returns intrinsic dimensions; the snippet emits `width`/`height` and a `<noscript>` fallback.
- Livereload is gated on the `risto.livereload` option rather than on `debug`.
- `updt-version` only commits when passed `--commit`.
- Vendored `file-templates/` and `tools/release/` as regular files instead of git submodules.

### Fixed

- Version constant is consistent across `config.php`, `header.php`, `footer.php` and `updt-version.js`. The template's `init-project` script renamed it in only two of those four places, which fataled every page load.
