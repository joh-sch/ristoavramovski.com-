#!/usr/bin/env node

/**
 * Deploys to a server over SSH.
 *
 *   npm run deploy           push, then update the server
 *   npm run deploy -- setup  first-time provisioning (clone, dirs, config)
 *   npm run deploy -- status show what the server is currently running
 *
 * Expects the server to have git, composer and npm, and to hold a clone of
 * the GitHub repo. Configure it in tools/deploy/deploy.config.json — copy
 * deploy.config.example.json and fill it in. That file is gitignored: it
 * names a host, which is nobody else's business.
 */

const fs = require('fs');
const path = require('path');
const { execFileSync, execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '../..');
const CONFIG_PATH = path.join(__dirname, 'deploy.config.json');

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
  return execSync(command, { cwd: ROOT, encoding: 'utf8', ...options }).trim();
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
    if [ -d "${cfg.path}/.git" ]; then
      echo "  repository already present — skipping clone"
    else
      mkdir -p "${cfg.path}"
      git clone --branch "${cfg.branch}" "${repo}" "${cfg.path}"
    fi

    cd "${cfg.path}"

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

    echo "→ npm"
    ${cfg.npm} ci --no-audit --no-fund
    ${cfg.npm} run build

    echo "→ runtime dirs"
    mkdir -p development-site/storage/accounts \
             development-site/storage/cache \
             development-site/storage/sessions \
             development-site/media \
             development-site/content/stream

    echo "→ clearing caches"
    rm -rf development-site/storage/cache/* || true

    # Thumbnails are derived from content and regenerate on demand. Clearing
    # them means a format or size change in config actually takes effect.
    rm -rf development-site/media/pages || true

    echo "→ deployed $(git rev-parse --short HEAD) ($(git log -1 --format=%s))"
  `);

  ok('Server updated');

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
    echo "assets   : $([ -f development-site/app/app.min.js ] && echo built || echo MISSING)"
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
