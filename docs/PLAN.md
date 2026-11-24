# Stream — Implementation Plan

The site is an artist's "stream of consciousness": a single public page with a
`+` button. Logged out, `+` opens a login dialog. Logged in, it opens a
composer that adds an image, text or link post to a column-masonry feed that
grows downward indefinitely.

Status: plan agreed 2026-11-24. Decisions locked in "Decisions" below.

---

## 1. Verified facts

These were measured or read out of the installed Kirby 5.6.4 source, not
assumed. They drive most of the design.

### 1.1 Page caching is automatically auth-correct

`Responder::cache()` contains the comment *"never ever cache private
responses"*. Reading a cookie flags the response private (`Cookie::get()` →
`Responder::usesCookie()`), and `Responder::isPrivate()` refuses to serve a
cached page to any request carrying that cookie.

Consequence: as soon as a template calls `$kirby->user()`, anonymous visitors
are served cached HTML and the logged-in artist always gets a fresh render.
No cache-key juggling, and the composer UI can never leak into an anonymous
cache entry. **The home template must call `$kirby->user()`.**

### 1.2 Login throttling is inherited

`$kirby->auth()->login()` routes through `validatePassword()`, which checks
`isBlocked()` and tracks failed attempts by IP *and* email.
Defaults: `auth.trials` = 10, `auth.timeout` = 3600s.

Consequence: a custom frontend login gets Panel-grade brute-force protection
for free. Do not reimplement it.

### 1.3 The scaling cliff is content reads, not page count

Benchmarked against synthetic posts on this machine (PHP 8.4, Kirby 5.6.4):

| Operation | 5,000 posts | 20,000 posts |
|---|---|---|
| Sort by `num` + take newest 30 | 7 ms | **56 ms** |
| Read content of those 30 | 13 ms | **41 ms** |
| Sort by `date` *content field* | **328 ms** | — |
| Filter by `type` *content field*, cold | — | **1,856 ms** |

Peak memory at 20k: 78 MB.

`num` lives in the directory name, so sorting and cursoring never open a file.
Any operation that touches every content file is ~30x slower and scales
linearly toward unusable.

> **THE RULE: never sort or filter the full post collection by a content
> field.** Order and paginate by `num` only. Cross-cutting queries (by type,
> by tag, search) go through the feed index (§5), never through
> `$pages->filterBy()`.

This also rules out Kirby's `num: default` mode, whose implementation runs
`parentModel()->children()->listed()->count()` on every create — O(N) per post.

### 1.4 HEIC cannot go through the thumb pipeline

`Kirby\Image\Image::$resizableTypes` and `$viewableTypes` are:

```
resizable: avif, jpg, jpeg, gif, png, webp
viewable:  avif, jpg, jpeg, gif, png, svg, webp
```

`heic`/`heif` are in neither. A HEIC upload therefore gets no thumbs, and
`lazyImg` falls back to the original URL — which no browser can display.

Consequence: HEIC must be converted **at upload time**, not by the thumb
driver. AVIF is first-class in Kirby 5 and is the conversion target.

### 1.5 Image driver status

- Kirby's `im` (ImageMagick CLI) driver is **deprecated as of Kirby 5.1.0**.
  Use `imagick` (the PHP extension) in `thumbs.driver`.
- This dev machine has **no** `imagick` extension and no ImageMagick binary.
  GD is present and reports `AVIF Support: true`, so AVIF *output* works
  locally; HEIC *input* does not.
- Production is assumed to have `imagick` with libheif.

Consequence: driver selection is capability-detected, and HEIC conversion
degrades with a clear error rather than a broken image.

---

## 2. Decisions

| Decision | Choice |
|---|---|
| Layout | True column masonry (shortest-column packing) |
| Storage | Year buckets |
| Auth scope | Single `artist` role |
| Editing | Delete from front page; edit in Panel |
| Artist account | Local test account for now; real account later |
| HEIC | Assume ImageMagick in production; convert to AVIF |

---

## 3. Content model

Each post is a Kirby page — that buys Panel editing, file attachments,
versioning and future permalinks.

Three templates rather than one page with a type switch, so adding
`post-video` or `post-quote` later is a new file, not a growing conditional:

- `post-image`
- `post-text`
- `post-link`

### Storage layout — year buckets

```
content/1_home/
  2026/
    202611241430_some-slug/
      post-image.txt
      photo.avif
```

Year buckets keep per-request Page-object count and memory bounded, keep the
Panel navigable, and make "load older" a walk backwards through buckets.

### Ordering

`num: datetime` (built-in, `YmdHi`) or a custom
`num: "{{ page.date.toDate('YmdHis') }}"` for second precision. Sorting and
pagination then read directory names only. See §1.3.

---

## 4. Routes

Under `/_api/` — page slugs cannot start with an underscore, so there is no
collision risk, and it avoids Kirby's reserved `/api` slug.

```
POST   /_api/session     login
DELETE /_api/session     logout
POST   /_api/posts       create
DELETE /_api/posts/:id   delete
GET    /_api/feed        next page (cursor)
```

All feed-reading logic lives in one service class so the storage layer can be
swapped later without touching a template.

---

## 5. Feed index

Filtering by type or tag — near-certain future features — costs 1.9s through
Kirby collections (§1.3). A small index file holds one row per post (num,
type, dimensions, thumb URL, excerpt), rebuilt incrementally by
`page.create:after` / `page.update:after` / `page.delete:after` hooks.

Built in v1 even though v1 does not filter, because retrofitting it later
means backfilling every post.

---

## 6. Auth and security

A single `artist` role scoped to creating posts — not a Panel admin.
`panel.install: false` in production. Panel stays at `/panel` for editing.

- CSRF validation on every mutating route via Kirby's `csrf()`
- Generic login errors that do not reveal whether an email exists
- Upload allowlist by extension *and* MIME, with a size cap, enforced in the
  blueprint's `accept` — not only client-side
- `secure` + `httponly` + `samesite` session cookies; HTTPS in production

---

## 7. Layout — column masonry

CSS `columns` is not usable: it fills column 1 before column 2, so an
endlessly growing feed runs the first column infinitely long and reads
chronologically downward instead of across.

CSS Grid with row-spans needs `grid-auto-flow: dense` to avoid gaps, and
`dense` backfills a *new* post into an *old* gap higher up the page — putting
new work above older work. Wrong for a chronological stream.

So: real shortest-column packing. The usual reason masonry is slow is waiting
for images to load before measuring them; that is avoided entirely because
dimensions are known server-side from `$file->width()/height()`. Packing is
computed from data, never from the DOM.

- The cursor carries per-column heights forward, so appending a page packs
  only the new tiles and never relayouts existing ones.
- Resize recomputes only when the column count crosses a breakpoint.

Exact mechanism (server-computed per-breakpoint assignment vs. a small JS
layout pass) is settled in phase 4.

---

## 8. Performance strategy

- Dimensions known server-side → exact `width`/`height` on every tile → no
  layout shift, and `content-visibility: auto` with an exact
  `contain-intrinsic-size` lets the browser skip off-screen tiles. Biggest
  win for a long feed, zero JS.
- Cursor-based pagination, never offset: offset degrades with depth and
  silently duplicates or skips items when a new post lands at the head
  mid-scroll. Cursor is the last-seen `num` plus per-column heights.
- Infinite scroll via an IntersectionObserver sentinel fetching an HTML
  partial, appending, then re-mounting gia components and refreshing lazyload
  on the new subtree only.
- Thumbs pre-generated on upload via `file.create:after`, so the first
  visitor does not pay for 30 thumbnail generations.
- DOM node count is the eventual ceiling, after thousands of tiles.
  `content-visibility` handles rendering cost but not node count. True
  virtualization breaks Ctrl+F and anchor links — reach for it only on
  measured need.
- Progressive enhancement: native `<dialog>` (free focus trapping, Esc,
  backdrop) and forms that post normally without JS.

---

## 9. Build phases

1. **Foundation** — `artist` role, blueprints, templates, year-bucket
   structure, `num` strategy, feed service class, local test account.
2. **Auth** — login/logout routes, CSRF, the `+` button's two states,
   login dialog.
3. **Composer** — create route, three post types, upload validation,
   HEIC→AVIF conversion, thumb pre-generation.
4. **Feed** — column masonry, cursor pagination, infinite scroll,
   `content-visibility`.
5. **Index + hooks** — incremental index maintenance, cache invalidation.
6. **Hardening** — seeded load test at ~10k posts to confirm the numbers hold
   in a real render, not just a query. Front-page delete.

---

## 10. Open risks

- **The `num`-only discipline is load-bearing and easy to violate.** One
  casually added `->filterBy('type')` reintroduces the 1.9s path. See §1.3.
- **Originals vs. derivatives.** Converting HEIC to AVIF in place destroys the
  archival original. For a repository of an artist's work that may matter;
  decide in phase 3 whether to retain originals as a secondary file.
- **EXIF rotation.** GD does not auto-rotate; phone photos can render
  sideways. Handled as part of the phase 3 conversion step.
- **DOM node count** at very large post counts — see §8.
