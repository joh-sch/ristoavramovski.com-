<?php

/**
 * Local development seeder.
 *
 * Creates the artist test account and fills the stream with posts.
 *
 *   php tools/dev/seed-stream.php            # test account + 7 sample posts
 *   php tools/dev/seed-stream.php 10000      # test account + N generated posts
 *   php tools/dev/seed-stream.php --reset    # wipe all posts first
 *
 * Never run this against production content.
 */

$root = dirname(__DIR__, 2) . '/development-site';
require $root . '/vendor/autoload.php';

use Risto\Stream\Stream;

$kirby = new Kirby([
    'roots' => [
        'index'    => $root,
        'storage'  => $root . '/storage',
        'accounts' => $root . '/storage/accounts',
        'cache'    => $root . '/storage/cache',
        'sessions' => $root . '/storage/sessions',
    ],
]);
$kirby->impersonate('kirby');

$args  = array_slice($argv, 1);
$reset = in_array('--reset', $args, true);
$count = (int) (array_values(array_filter($args, 'ctype_digit'))[0] ?? 0);

// ── reset ───────────────────────────────────────────────────────────────────

if ($reset === true) {
    foreach (Stream::buckets() as $bucket) {
        $bucket->delete(true);
        echo "  - removed bucket {$bucket->slug()}\n";
    }
}

// ── test account ────────────────────────────────────────────────────────────

const TEST_EMAIL = 'artist@risto.test';

/**
 * Never hardcode the password here.
 *
 * This repository is public, so a literal would be published — and the real
 * risk is not this throwaway .test account but the habit: the same string
 * ends up on the server when the artist's account is created.
 *
 * Set STREAM_SEED_PASSWORD to choose one, otherwise a random password is
 * generated and printed once.
 */
if ($kirby->user(TEST_EMAIL) === null) {
    $password = getenv('STREAM_SEED_PASSWORD') ?: bin2hex(random_bytes(9));

    $user = $kirby->users()->create([
        'email'    => TEST_EMAIL,
        'password' => $password,
        'role'     => 'artist',
        'name'     => 'Test Artist',
        'language' => 'en',
    ]);

    echo "  + user {$user->email()} (role: artist)\n";
    echo "    password: {$password}\n";
    echo "    ↑ shown once — note it down, or set STREAM_SEED_PASSWORD next time\n";
} else {
    echo "  = user " . TEST_EMAIL . " already exists (password unchanged)\n";
}

// ── helpers ─────────────────────────────────────────────────────────────────

function bucketFor(DateTimeImmutable $date): Kirby\Cms\Page
{
    $slug   = Stream::bucketSlug($date);
    $bucket = page(Stream::ROOT_ID . '/' . $slug);

    if ($bucket === null) {
        $bucket = page(Stream::ROOT_ID)->createChild([
            'slug'     => $slug,
            'template' => 'stream-year',
            'content'  => ['title' => $slug],
        ])->changeStatus('unlisted');
        echo "  + bucket {$slug}\n";
    }

    return $bucket;
}

function makePost(DateTimeImmutable $date, string $template, array $fields): void
{
    $bucket = bucketFor($date);
    $slug   = 'post-' . $date->format('YmdHis');

    if (page($bucket->id() . '/' . $slug) !== null) {
        return;
    }

    $bucket->createChild([
        'slug'     => $slug,
        'template' => $template,
        'content'  => $fields + [
            'date'    => $date->format('Y-m-d H:i:s'),
            'sortkey' => Stream::num($date),
            'size'    => 'm',
        ],
    ])->changeStatus('listed');
}

// ── sample content ──────────────────────────────────────────────────────────

if ($count === 0) {
    $samples = [
        ['2026-11-24 14:30:00', 'post-text', ['title' => 'On repetition',   'text' => 'Thinking about repetition as a form of attention.']],
        ['2026-11-23 09:12:00', 'post-link', ['title' => 'Reference one',   'url'  => 'https://example.org/reference-one']],
        ['2026-11-20 18:45:00', 'post-text', ['title' => 'Unfinished',      'text' => 'A note left unfinished on purpose.']],
        ['2026-10-02 11:00:00', 'post-text', ['title' => 'Colour studies',  'text' => 'Colour studies, late afternoon.']],
        ['2026-08-14 16:20:00', 'post-link', ['title' => 'Reference two',   'url'  => 'https://example.org/reference-two']],
        ['2025-12-30 10:05:00', 'post-text', ['title' => 'Last of the year','text' => 'Last entry of the year.']],
        ['2025-06-11 08:00:00', 'post-text', ['title' => 'Early summer',    'text' => 'Early summer, nothing resolved.']],
    ];

    foreach ($samples as [$when, $template, $fields]) {
        makePost(new DateTimeImmutable($when), $template, $fields);
    }

    echo "  + " . count($samples) . " sample posts\n";
    exit(0);
}

// ── bulk generation (load testing) ──────────────────────────────────────────

$start = new DateTimeImmutable('2020-01-01 09:00:00');

// Deterministic variety: the masonry layout is only exercised by tiles of
// differing heights, spans and types.
$sizes  = ['s', 'm', 'm', 'l', 'm', 's'];
$lengths = [4, 12, 30, 8, 60, 18];

for ($i = 0; $i < $count; $i++) {
    $date = $start->add(new DateInterval('PT' . ($i * 3) . 'H'));
    $size = $sizes[$i % count($sizes)];

    if ($i % 7 === 3) {
        makePost($date, 'post-link', [
            'title' => 'Reference ' . $i,
            'url'   => 'https://example.org/reference-' . $i,
            'text'  => 'Reference ' . $i,
            'size'  => $size,
        ]);
        continue;
    }

    makePost($date, 'post-text', [
        'title' => 'Post ' . $i,
        'text'  => 'Generated post ' . $i . '. ' . str_repeat('lorem ipsum dolor sit amet ', $lengths[$i % count($lengths)]),
        'size'  => $size,
    ]);

    if ($i > 0 && $i % 500 === 0) {
        echo "  … {$i}/{$count}\n";
    }
}

echo "  + {$count} generated posts\n";
