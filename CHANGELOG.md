# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

While the major version is `0`, the public surface — content model, routes and
blueprints — may change in any minor release. See `docs/RELEASING.md`.

## [Unreleased]

## [0.1.0] — 2026-11-24

First release. The site is a usable foundation: the artist can log in, post,
draw, and everything is deployable.

### Added

- **Stream.** Posts are Kirby pages in year buckets, ordered by the folder
  number so pagination never opens a content file. Image, text and link types,
  each its own template.
- **Feed.** Cursor-based pagination and column-masonry layout with
  shortest-column packing, infinite scroll, and `content-visibility` so an
  endlessly growing feed stays cheap to render.
- **Front-end authentication.** A `+` button that opens a login dialog when
  logged out and a composer when logged in. Both work without JavaScript.
- **Composer.** Create image, text and link posts, with uploads validated by
  real extension and sniffed content rather than the browser's Content-Type.
- **Images.** Thumbnails pre-generated on upload; HEIC converted on arrival,
  since Kirby excludes it from the thumbnail pipeline entirely. Format is
  detected from what the installed driver can actually write.
- **Drawing layer.** A pencil tool backed by Fabric, loaded on demand as a
  separate bundle. Saved drawings render as sanitised inline SVG, so visitors
  see them without downloading the editor.
- **Colour controls.** Per-visitor background and text colour, stored locally
  and applied before first paint.
- **Deployment.** `npm run deploy` builds locally, pushes, and updates the
  server over SSH. `docs/DEPLOYMENT.md` covers the full setup.
- **Documentation.** `docs/PLAN.md` records the architecture and the
  measurements behind it; `CLAUDE.md` records the traps worth knowing.

### Security

- CSRF validation on every mutating endpoint.
- Login throttling, session regeneration and a random failure delay, inherited
  from Kirby's own `Auth` rather than reimplemented.
- Drawing SVG sanitised before storage — it is inlined into every visitor's
  page, so it is treated as untrusted even though it comes from the artist.
- `.htaccess` blocks `content/`, `site/`, `storage/`, `kirby/`, `vendor/`, the
  Composer manifests and the unbundled front-end sources.

### Fixed

Bugs found by testing against a running site rather than by review:

- Posts created in the same second shared a sort number, and the feed cursor
  advances with `num < last` — so one post was silently and permanently
  skipped.
- Kirby's date field rounds to five minutes by default. Since the update hook
  recomputes the sort key from the date, the first Panel edit of any post
  moved it in the feed.
- `num: "{{ page.date.toDate('YmdHis') }}"` is silently wrong under this
  project's `intl` date handler, where `m` means *minute*: it produced
  `2026452018` instead of `20261120184500`.
- Nesting one gia component inside another made the outer one adopt the
  inner's refs, firing a phantom login on every new post.
- `$file->thumb()` returns a lazy object; without `->save()` pre-generation
  was a silent no-op.
- Deriving image tile heights from aspect ratio dropped the caption, so tiles
  overlapped by exactly the caption height.
- `$errors[null]` is a 500 on PHP 8.5 rather than a notice.

[Unreleased]: https://github.com/joh-sch/ristoavramovski.com-/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/joh-sch/ristoavramovski.com-/releases/tag/v0.1.0
