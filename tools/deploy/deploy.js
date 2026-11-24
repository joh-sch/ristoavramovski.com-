#!/usr/bin/env node

/**
 * Deploys to a server over SSH.
 *
 *   npm run deploy           push, then update the server
 *   npm run deploy -- setup  first-time provisioning (clone, dirs, config)
 *   npm run deploy -- status show what the server is currently running
 *
 * Expects the server to have git, composer and rsync, and to hold a clone of
 * the GitHub repo. Configure it in tools/deploy/deploy.config.json — copy
 * deploy.config.example.json and fill it in. That file is gitignored: it
 * names a host, which is nobody else's business.
 *
 * Node is NOT required on the server. This host (All-Inkl shared hosting) has
 * git, composer 2 and rsync but no node, so CSS and JS are built here and the
 * two artifacts are rsynced across. They stay out of git either way.
 */

const fs = require('fs');
const path = require('path');
const { execFileSync, execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '../..');
const CONFIG_PATH = path.join(__dirname, 'deploy.config.json');

/**
 * Built artifacts, shipped by rsync rather than git.
 *
 * Gitignored on purpose — generated files in a repository produce a diff on
 * every build and conflict on every merge. The server cannot build them
 * itself (no node), so they travel separately.
 */
const ASSETS = [
  'development-site/app/app.min.js',
  'development-site/app/drawing.min.js',
  'development-site/app/styles/styles.css',
];

// ─── output ──────────────────────────────────────────────────────────────────

const step = (msg) => console.log(`\n  ${msg}`);
const ok = (msg) => console.log(`  ✔  ${msg}`);
const warn = (msg) => console.log(`  ⚠  ${msg}`);

function abort(msg) {
  console.error(`\n  ✖  ${msg}\n`);
  process.exit(1);
}

// ─── config ──────────────────────────────────────────────────────────────────

function config() {
  if (fs.existsSync(CONFIG_PATH) === false) {
    abort(
      'No deploy config found.\n\n' +
      '     cp tools/deploy/deploy.config.example.json tools/deploy/deploy.config.json\n\n' +
      '     then fill in host, path, branch and url.',
    );
  }

  const cfg = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8'));

  for (const key of ['host', 'path', 'branch', 'url']) {
    if (!cfg[key] || String(cfg[key]).includes('example')) {
      abort(`deploy.config.json: "${key}" is still a placeholder.`);
    }
  }

  return {
    port: 22,
    remote: 'origin',
    php: 'php',
    composer: 'composer',
    npm: 'npm',
    ...cfg,
  };
}

// ─── ssh ─────────────────────────────────────────────────────────────────────

function ssh(cfg, script, { capture = false } = {}) {
  const args = ['-p', String(cfg.port), cfg.host, 'bash -se'];

  return execFileSync('ssh', args, {
    input: `set -euo pipefail\n${script}\n`,
    stdio: capture ? ['pipe', 'pipe', 'inherit'] : ['pipe', 'inherit', 'inherit'],
    encoding: 'utf8',
  });
}

function local(command, options = {}) {
  const out = execSync(command, { cwd: ROOT, encoding: 'utf8', ...options });

  // execSync returns null when stdio is inherited, because nothing is
  // captured — so this cannot assume a string.
  return typeof out === 'string' ? out.trim() : '';
}

// ─── preflight ───────────────────────────────────────────────────────────────

function preflight(cfg) {
  step('Checking the working tree…');

  if (local('git status --porcelain') !== '') {
    abort('You have uncommitted changes. Commit or stash them first —\n     the server deploys from the remote, so they would not ship anyway.');
  }

  const branch = local('git rev-parse --abbrev-ref HEAD');

  if (branch !== cfg.branch) {
    abort(`On branch "${branch}" but the server tracks "${cfg.branch}".`);
  }

  try {
    local(`git remote get-url ${cfg.remote}`, { stdio: 'pipe' });
  } catch {
    abort(`No git remote named "${cfg.remote}". Add it, then try again.`);
  }

  ok(`Clean tree on ${branch}`);
}

// ─── commands ────────────────────────────────────────────────────────────────

function setup(cfg) {
  const repo = local(`git remote get-url ${cfg.remote}`);

  console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('  First-time server setup');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log(`  host : ${cfg.host}`);
  console.log(`  path : ${cfg.path}`);
  console.log(`  repo : ${repo}`);

  step('Preparing the server…');

  ssh(cfg, `
    mkdir -p "${cfg.path}"
    cd "${cfg.path}"

    # Test for a real commit, not just .git: an interrupted first run leaves
    # an initialised repo with no HEAD, which would otherwise be mistaken for
    # a working checkout and silently skipped forever.
    if git rev-parse --verify HEAD >/dev/null 2>&1; then
      echo "  repository already checked out — skipping"
    else
      # init + fetch rather than clone: the hosting panel creates the
      # document-root directory when the domain is pointed at it, and
      # git clone refuses a non-empty target.
      git init -q
      git remote add origin "${repo}" 2>/dev/null || git remote set-url origin "${repo}"
      git fetch --quiet origin "${cfg.branch}"
      git checkout -b "${cfg.branch}" "origin/${cfg.branch}"
      echo "  checked out ${cfg.branch}"
    fi

    # Writable runtime state. These are gitignored, so they never arrive with
    # a deploy and must exist before Kirby boots.
    mkdir -p development-site/storage/accounts \
             development-site/storage/cache \
             development-site/storage/sessions \
             development-site/media \
             development-site/content/stream

    echo "  runtime directories ready"
  `);

  ok('Server prepared');

  console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('  Still to do by hand, once:');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log(`  1. Point the vhost document root at ${cfg.path}/development-site`);
  console.log('  2. Create the environment config on the server:');
  console.log(`       ${cfg.path}/development-site/site/config/config.<hostname>.php`);
  console.log('     (see docs/DEPLOYMENT.md — it is gitignored on purpose)');
  console.log('  3. Run: npm run deploy');
  console.log('  4. Open /panel, create the artist account, then set');
  console.log('     panel.install back to false in that config');
  console.log();
}

function deploy(cfg) {
  preflight(cfg);

  step('Building assets locally…');
  local('npm run build', { stdio: 'inherit' });

  for (const asset of ASSETS) {
    if (fs.existsSync(path.join(ROOT, asset)) === false) {
      abort(`Build did not produce ${asset}.`);
    }
  }
  ok('Assets built');

  step(`Pushing ${cfg.branch} to ${cfg.remote}…`);
  local(`git push ${cfg.remote} ${cfg.branch}`, { stdio: 'inherit' });
  ok('Pushed');

  step('Updating the server…');

  ssh(cfg, `
    cd "${cfg.path}"

    echo "→ fetching"
    git fetch --prune ${cfg.remote}

    # Hard reset rather than pull: the server never has local commits, and a
    # merge conflict mid-deploy would leave the site half-updated. Content is
    # gitignored, so posts are untouched by this.
    git reset --hard ${cfg.remote}/${cfg.branch}

    echo "→ composer"
    cd development-site
    ${cfg.composer} install --no-dev --optimize-autoloader --no-interaction
    cd ..

    echo "→ runtime dirs"
    mkdir -p development-site/storage/accounts \
             development-site/storage/cache \
             development-site/storage/sessions \
             development-site/media \
             development-site/content/stream \
             development-site/content/drawing

    # Seed the drawing page only if absent — it holds the artist's drawing and
    # must survive every deploy.
    [ -f development-site/content/drawing/drawing.txt ] || \
      printf 'Title: Drawing\n' > development-site/content/drawing/drawing.txt

    echo "→ clearing caches"
    rm -rf development-site/storage/cache/* || true

    # Thumbnails are derived from content and regenerate on demand. Clearing
    # them means a format or size change in config actually takes effect.
    rm -rf development-site/media/pages || true

    echo "→ deployed $(git rev-parse --short HEAD) ($(git log -1 --format=%s))"
  `);

  ok('Server updated');

  // After the git reset, not before: the reset would wipe them otherwise,
  // since they are gitignored and therefore untracked on the server too.
  step('Copying built assets…');

  for (const asset of ASSETS) {
    const target = `${cfg.path}/${path.dirname(asset)}/`;

    execFileSync('rsync', [
      '-az',
      '-e', `ssh -p ${cfg.port}`,
      path.join(ROOT, asset),
      `${cfg.host}:${target}`,
    ], { stdio: 'inherit' });
  }

  ok(`Copied ${ASSETS.length} assets`);

  step('Checking the site responds…');

  try {
    const status = local(`curl -s -o /dev/null -w '%{http_code}' --max-time 20 "${cfg.url}"`);

    if (status === '200') {
      ok(`${cfg.url} → 200`);
    } else {
      warn(`${cfg.url} → ${status} (deployment finished, but the site is not answering 200)`);
    }
  } catch {
    warn(`Could not reach ${cfg.url}`);
  }

  console.log();
}

function status(cfg) {
  step(`${cfg.host}:${cfg.path}`);

  const out = ssh(cfg, `
    cd "${cfg.path}"
    echo "commit   : $(git rev-parse --short HEAD) $(git log -1 --format='%s (%cr)')"
    echo "branch   : $(git rev-parse --abbrev-ref HEAD)"
    echo "kirby    : $(${cfg.php} -r 'require "development-site/vendor/autoload.php"; echo \\Kirby\\Cms\\App::version();' 2>/dev/null || echo 'not installed')"
    echo "posts    : $(find development-site/content/stream -mindepth 2 -maxdepth 2 -type d 2>/dev/null | wc -l | tr -d ' ')"
    echo "js       : $([ -f development-site/app/app.min.js ] && echo \"$(date -r development-site/app/app.min.js '+%Y-%m-%d %H:%M')\" || echo MISSING)"
    echo "css      : $([ -f development-site/app/styles/styles.css ] && echo \"$(date -r development-site/app/styles/styles.css '+%Y-%m-%d %H:%M')\" || echo MISSING)"
  `, { capture: true });

  console.log(out.split('\n').filter(Boolean).map((l) => `  ${l}`).join('\n'));
  console.log();
}

// ─── run ─────────────────────────────────────────────────────────────────────

const command = process.argv[2] || 'deploy';
const cfg = config();

try {
  if (command === 'setup') setup(cfg);
  else if (command === 'status') status(cfg);
  else if (command === 'deploy') deploy(cfg);
  else abort(`Unknown command "${command}". Use: deploy | setup | status`);
} catch (err) {
  abort(err.message || String(err));
}
