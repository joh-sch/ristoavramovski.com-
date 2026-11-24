#!/usr/bin/env node

/**
 * Version bump utility
 *
 * Usage:
 *   npm run updt-version 1.2.3            — set explicit version
 *   npm run updt-version patch            — bump patch  0.5.1 → 0.5.2
 *   npm run updt-version minor            — bump minor  0.5.1 → 0.6.0
 *   npm run updt-version major            — bump major  0.5.1 → 1.0.0
 *
 *   Add --commit to stage and commit the changed files:
 *   npm run updt-version patch --commit
 *
 * Files updated:
 *   package.json                              "version"
 *   package-lock.json                         "version"
 *   development-site/site/config/config.php   RISTO_2026_VERSION constant
 */

const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");

const ROOT = path.resolve(__dirname, "../..");
const SEMVER_RE = /^\d+\.\d+\.\d+$/;
const VERSION_CONST = "RISTO_2026_VERSION";

const FILES = {
  pkg: path.join(ROOT, "package.json"),
  lock: path.join(ROOT, "package-lock.json"),
  config: path.join(ROOT, "development-site/site/config/config.php"),
};

// ─── helpers ─────────────────────────────────────────────────────────────────

function readFile(p) {
  return fs.readFileSync(p, "utf8");
}
function writeFile(p, content) {
  fs.writeFileSync(p, content, "utf8");
}

function replaceOrDie(content, re, replacement, label) {
  if (!re.test(content)) {
    console.error(`✖  Could not find "${label}" — aborting.`);
    process.exit(1);
  }
  return content.replace(re, replacement);
}

function bump(current, part) {
  const [major, minor, patch] = current.split(".").map(Number);
  switch (part) {
    case "major":
      return `${major + 1}.0.0`;
    case "minor":
      return `${major}.${minor + 1}.0`;
    case "patch":
      return `${major}.${minor}.${patch + 1}`;
    default:
      throw new Error(`Unknown bump type: ${part}`);
  }
}

// ─── resolve arguments ───────────────────────────────────────────────────────

const argv = process.argv.slice(2);
const shouldCommit = argv.includes("--commit") || process.env.npm_config_commit === "true";
const arg = argv.find((a) => !a.startsWith("--"));

if (!arg) {
  console.error("Usage: npm run updt-version <major|minor|patch|x.y.z> [--commit]");
  process.exit(1);
}

const pkg = JSON.parse(readFile(FILES.pkg));
const currentVer = pkg.version;
let nextVer;

if (SEMVER_RE.test(arg)) {
  nextVer = arg;
} else if (["major", "minor", "patch"].includes(arg)) {
  nextVer = bump(currentVer, arg);
} else {
  console.error(`✖  Invalid argument "${arg}". Provide a semver string or one of: major, minor, patch`);
  process.exit(1);
}

if (nextVer === currentVer) {
  console.log(`Version is already ${currentVer} — nothing to do.`);
  process.exit(0);
}

// ─── update package.json ─────────────────────────────────────────────────────

pkg.version = nextVer;
writeFile(FILES.pkg, JSON.stringify(pkg, null, 2) + "\n");
console.log(`package.json          "version"           ${currentVer} → ${nextVer}`);

if (fs.existsSync(FILES.lock)) {
  const lock = JSON.parse(readFile(FILES.lock));
  lock.version = nextVer;
  if (lock.packages?.[""]) lock.packages[""].version = nextVer;
  writeFile(FILES.lock, JSON.stringify(lock, null, 2) + "\n");
  console.log(`package-lock.json     "version"           ${currentVer} → ${nextVer}`);
}

// ─── update config.php ───────────────────────────────────────────────────────

let config = readFile(FILES.config);
config = replaceOrDie(
  config,
  new RegExp(`(define\\('${VERSION_CONST}',\\s*')[^']+(')`),
  `$1${nextVer}$2`,
  `${VERSION_CONST} in config.php`,
);
writeFile(FILES.config, config);
console.log(`config.php            ${VERSION_CONST}   ${currentVer} → ${nextVer}`);

// ─── optional commit ─────────────────────────────────────────────────────────

if (shouldCommit) {
  const changedFiles = [FILES.pkg, FILES.lock, FILES.config]
    .filter((filePath) => fs.existsSync(filePath))
    .map((filePath) => path.relative(ROOT, filePath));

  try {
    execFileSync("git", ["add", "--", ...changedFiles], { cwd: ROOT, stdio: "inherit" });
    execFileSync("git", ["commit", "-m", `chore(release): bump version to ${nextVer}`, "--", ...changedFiles], {
      cwd: ROOT,
      stdio: "inherit",
    });
  } catch {
    console.error("⚠  Commit failed — the files were updated, commit them manually.");
  }
}

console.log(`\nVersion bumped: ${currentVer} → ${nextVer}`);
if (!shouldCommit) console.log("(not committed — re-run with --commit to stage and commit)");
