#!/usr/bin/env node

/**
 * Kirby 5 installation utility
 *
 * Installs Kirby 5 into the development-site/ directory via Composer and
 * creates the writable runtime directories that index.php points its roots at.
 *
 * Usage: npm run init-kirby
 *
 * Requirements: PHP >= 8.2, Composer (https://getcomposer.org/)
 */

const { execFileSync, execSync } = require("child_process");
const path = require("path");
const fs = require("fs");

const ROOT = path.resolve(__dirname, "../..");
const DEST = path.join(ROOT, "development-site");

// ─── helpers ─────────────────────────────────────────────────────────────────

function step(msg) {
  console.log(`\n  ${msg}`);
}
function ok(msg) {
  console.log(`  ✔  ${msg}`);
}
function abort(msg) {
  console.error(`\n  ✖  ${msg}\n`);
  process.exit(1);
}

// ─── preflight ───────────────────────────────────────────────────────────────

if (fs.existsSync(path.join(DEST, "vendor/autoload.php"))) {
  abort("Kirby is already installed (vendor/autoload.php exists).\n     Remove development-site/vendor/ first to reinstall.");
}

try {
  execFileSync("php", ["--version"], { stdio: "pipe" });
} catch {
  abort("PHP is not available in PATH. Install PHP >= 8.2 first.");
}

try {
  execFileSync("composer", ["--version"], { stdio: "pipe" });
} catch {
  abort("Composer is not available in PATH. Install it from https://getcomposer.org/");
}

// ─── main ────────────────────────────────────────────────────────────────────

async function main() {
  console.log("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
  console.log("  Kirby 5 Installation");
  console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

  const hasLock = fs.existsSync(path.join(DEST, "composer.lock"));

  step(
    hasLock
      ? "composer.lock found — installing the exact pinned versions…"
      : "No composer.lock yet — resolving and writing one…",
  );

  try {
    execSync("composer install --no-interaction", { cwd: DEST, stdio: "inherit" });
  } catch (err) {
    abort(`composer install failed: ${err.message}`);
  }
  ok("Kirby 5 installed via Composer.");

  if (!hasLock) {
    ok("composer.lock written — commit it so every environment installs the same Kirby version.");
  }

  // Writable runtime directories — must match the roots defined in index.php.
  const runtimeDirs = [
    path.join(DEST, "media"),
    path.join(DEST, "storage/accounts"),
    path.join(DEST, "storage/cache"),
    path.join(DEST, "storage/sessions"),
  ];
  for (const dir of runtimeDirs) {
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  }
  ok("Runtime directories created (media/, storage/).");

  console.log("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
  console.log("  Done! Next steps:");
  console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
  console.log("  1. Point your local PHP server (MAMP/Valet/Herd) at development-site/");
  console.log("  2. Copy site/config/config.localhost.php.example → site/config/config.localhost.php");
  console.log("     (rename to match your local hostname if it is not 'localhost')");
  console.log("  3. Open /panel to create the first admin user,");
  console.log("     then set panel.install back to false in the local config");
  console.log("  4. Run: npm run watch  to start the JS/CSS watchers");
  console.log();
}

main().catch((err) => {
  console.error(`\n✖  Fatal: ${err.message}\n`);
  process.exit(1);
});
