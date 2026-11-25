# Releasing

A release is a git tag plus a GitHub release whose notes come from
`CHANGELOG.md`. It does **not** deploy — deploying is `npm run deploy`, and
the two are deliberately separate so a tag can be cut without touching the
server, and the server can be updated without cutting a tag.

```sh
npm run updt-version minor      # or patch / major / an explicit x.y.z
# write the changelog entry for that version
git commit -am "chore(release): 0.2.0"
git push origin devel
npm run release
```

---

## 1. Versioning

[Semantic Versioning](https://semver.org/spec/v2.0.0.html). While the major
version is `0`, the public surface may change in any minor release — so the
practical rule for now is:

| Change | Bump |
|---|---|
| Content model, routes, blueprints, anything needing migration | **minor** |
| Features that only add | **minor** |
| Fixes, copy, styling, tooling | **patch** |

`1.0.0` is for when the content model is settled and the site is live for
real. Until then, breaking changes go in minor releases and are listed under
**Changed** with a note on what to migrate.

`npm run updt-version` keeps three places in step:

- `package.json` and `package-lock.json`
- `RISTO_2026_VERSION` in `site/config/config.php` — the cache-busting token
  on the CSS and JS

It does not commit unless given `--commit`.

---

## 2. The changelog

[Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Entries are grouped
under **Added**, **Changed**, **Deprecated**, **Removed**, **Fixed** and
**Security**, newest version first, with an `[Unreleased]` section at the top
to collect work in progress.

Two rules worth keeping:

- **Write for the person reading it later**, not for the diff. "Posts in the
  same second shared a sort number, so the feed skipped one" beats "fix num
  collision".
- **The version heading must match `package.json` exactly.** The release tool
  refuses to run otherwise, because the GitHub release notes are read straight
  out of the matching section.

Remember the compare links at the bottom when adding a version.

---

## 3. Cutting the release

`npm run release` runs `tools/release/release.js`, which refuses to continue
unless:

1. the working tree is clean;
2. the local branch (`devel`, per the `release` key in `package.json`) is
   exactly in sync with `origin` — neither ahead nor behind;
3. `package.json`'s version has a matching `## [x.y.z]` heading in the
   changelog, with a non-empty body;
4. the tag does not already exist.

It then tags (`v` prefix), pushes the tag, and creates the GitHub release with
the changelog section as its notes.

Every one of those checks exists to stop a tag pointing at something other
than what the changelog claims.

---

## 4. After a release

Deploy separately when you want the server updated:

```sh
npm run deploy
```

The deployed commit is whatever `devel` points at, which after a release is
the release commit. `npm run deploy -- status` prints what the server is
actually running.

---

## 5. If something goes wrong

**Tag pushed by mistake:**

```sh
git tag -d v0.2.0
git push origin :refs/tags/v0.2.0
gh release delete v0.2.0 --yes
```

**Version and changelog disagree** — the tool aborts before tagging, so just
fix whichever is wrong and run it again.

**Branch out of sync** — push or pull first. The tool will not tag a commit
that is not on the remote, because the GitHub release would point at a commit
nobody else can see.
