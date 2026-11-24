# Deployment

Deploys run from your machine over SSH. The server holds a clone of the
GitHub repo and installs Kirby itself via Composer.

**CSS and JS are built locally and rsynced**, because this host has git,
Composer 2 and rsync but **no node**. They are gitignored either way —
generated files in a repository diff on every build and conflict on every
merge.

```sh
npm run deploy
```

That pushes the current branch, updates the server, rebuilds, clears caches
and checks the site answers 200.

---

## 1. What ships, and what does not

| | Tracked in git | Lives only on the server |
|---|---|---|
| Templates, snippets, plugins, blueprints | ✅ | |
| System pages (home, error, login, compose, stream root) | ✅ | |
| `composer.lock`, `package-lock.json` | ✅ | |
| **Posts** (`content/stream/<year>/…`) | | ✅ |
| `vendor/` (Kirby) | | ✅ composer, on the server |
| `app.min.js`, `styles.css` | | ✅ built locally, rsynced |
| `media/` (thumbnails) | | ✅ regenerated |
| `storage/` (accounts, cache, sessions) | | ✅ |
| `config.<hostname>.php` | | ✅ |

**Posts are deliberately untracked.** The artist creates them on the server,
so they are data, not source — the equivalent of database rows. If they were
tracked, every deploy (`git reset --hard`) would overwrite or delete whatever
had been posted since the last one.

The consequence is that **git is not a backup of the posts.** See §5.

---

## 2. One-time setup

### 2.1 GitHub

Create a **private** repository and add it as the remote:

```sh
git remote add origin git@github.com:<you>/<repo>.git
git push -u origin main
```

The server pulls from this repo, so it needs read access — either a deploy
key (recommended: read-only, per-repo) or an SSH agent forwarded from your
machine.

### 2.2 Configure the target

```sh
cp tools/deploy/deploy.config.example.json tools/deploy/deploy.config.json
```

Fill in:

```json
{
  "host": "user@server.example.com",
  "port": 22,
  "path": "/var/www/stream.example.com",
  "branch": "main",
  "url": "https://stream.example.com"
}
```

This file is gitignored — it names a host and a path.

### 2.3 Provision the server

```sh
npm run deploy -- setup
```

Clones the repo and creates the writable runtime directories. Then, by hand:

1. **Point the document root at `<path>/development-site`** — not at the
   repository root. Everything above `development-site/` (including `tools/`,
   `docs/` and `.git/`) must stay outside the web root, or `/.git/config` is
   downloadable by anyone.

   On All-Inkl this is the subdomain's *Verzeichnis* in KAS: set it to
   `<domain-folder>/development-site`. Several existing sites on this account
   already use that nested layout.
2. **Create the environment config** on the server:

   ```sh
   cp development-site/site/config/config.production.php.example \
      development-site/site/config/config.stream.example.com.php
   ```

   The filename must match the request hostname without port. Kirby loads it
   by host and merges it over `config.php`.
3. **Deploy**: `npm run deploy`
4. **Create the artist account**: set `panel.install` to `true` in that
   config, open `/panel`, create the account, then set it back to `false`.

### 2.4 Check it

```sh
npm run deploy -- status
```

Prints the deployed commit, Kirby version, post count and whether the assets
were built.

---

## 3. Everyday deploys

```sh
npm run deploy
```

Refuses to run with an uncommitted working tree, or when your branch does not
match the one the server tracks — the server deploys from the remote, so
uncommitted work would silently not ship.

It builds the assets locally first, pushes, then on the server fetches,
hard-resets to the remote branch, runs `composer install --no-dev`, recreates
runtime directories and clears the page cache and generated thumbnails —
and finally rsyncs the built CSS and JS across. The rsync happens *after* the
reset, which would otherwise wipe them.

The hard reset is deliberate: the server never has local commits, and a merge
conflict mid-deploy would leave the site half-updated.

---

## 4. Caching

The production config turns on Kirby's page cache. Two things make that safe,
both verified rather than assumed:

- **Anonymous visitors get cached HTML; the artist never does.** Reading a
  cookie marks a response private, and Kirby refuses both to serve a cached
  page to someone holding that cookie and to cache a private response. The
  home template calls `$kirby->user()`, which is what triggers this.
  See `docs/PLAN.md` §1.1.
- **New posts invalidate the cache automatically.** Kirby's `ModelCommit`
  flushes the pages cache after any model action, so no custom hook is
  needed.

---

## 5. Backups

Git does not back up the posts. Back up from the server:

```sh
rsync -az --delete \
  user@server:/var/www/stream.example.com/development-site/content/ \
  ./backups/content/

rsync -az \
  user@server:/var/www/stream.example.com/development-site/storage/accounts/ \
  ./backups/accounts/
```

`content/` holds the posts and their original uploads; `storage/accounts/`
holds the artist's login. `media/` needs no backup — it regenerates.

---

## 6. Before this becomes a public site

- **Kirby needs a licence.** It runs unlicensed, with a banner in the Panel.
  Fine for a test server; buy one before launch. <https://getkirby.com/buy>
- **HTTPS**, so the session cookie is only sent over TLS.
- **HEIC support.** Verify the server's ImageMagick has the `heic` delegate,
  or iPhone uploads are refused. `npm run deploy -- status` does not check
  this; see `docs/PLAN.md` §1.4–1.5.
- **`panel.install` is `false`.**
- **Check what is reachable**: `/storage`, `/content/*.txt`, `/site/*` and
  `/vendor/*` must all be blocked. `development-site/.htaccess` does this on
  Apache. **On nginx none of it applies** — the rules must be ported.

---

## 7. Troubleshooting

**"You have uncommitted changes"** — the server deploys from the remote, so
commit or stash first.

**Site 500s after deploy** — check the environment config exists and is named
for the request hostname. Without it, `debug` stays `false` and Kirby reports
nothing useful; temporarily set `debug => true` there to see the error.

**Changes do not appear** — the page cache. A deploy clears it; if you edited
content directly on disk rather than through the Panel, clear it by hand:
`rm -rf development-site/storage/cache/*`.

**Images 404 after a format change** — thumbnails are keyed by their options.
A deploy clears `media/pages`, so they regenerate on next request.
