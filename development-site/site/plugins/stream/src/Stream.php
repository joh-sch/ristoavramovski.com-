<?php

namespace Risto\Stream;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Exception\NotFoundException;

/**
 * Structural facts about the post stream.
 *
 * Posts live in year buckets beneath a dedicated root page:
 *
 *   content/stream/2026/202611241430_some-slug/post-image.txt
 *
 * The root is deliberately separate from the home page: home is a *view* of
 * the stream, the stream is the *data*. That keeps home's own content fields
 * free and gives posts a sane permalink shape for later.
 */
final class Stream
{
    public const ROOT_ID = 'stream';

    public const POST_TEMPLATES = [
        'post-image',
        'post-text',
        'post-link',
    ];

    /**
     * The stream root page.
     *
     * @throws NotFoundException if the content folder is missing
     */
    public static function root(): Page
    {
        $root = page(self::ROOT_ID);

        if ($root === null) {
            throw new NotFoundException(
                message: 'Stream root page "' . self::ROOT_ID . '" not found. ' .
                         'Expected content/' . self::ROOT_ID . '/.'
            );
        }

        return $root;
    }

    /**
     * Year buckets, newest first.
     *
     * Filtering and sorting happen on the slug, which Kirby derives from the
     * directory name — no content file is opened. See docs/PLAN.md §1.3.
     */
    public static function buckets(): Pages
    {
        return self::root()
            ->children()
            ->filter(fn (Page $page) => self::isBucket($page))
            ->sortBy('slug', 'desc');
    }

    /**
     * Is this page a year bucket?
     *
     * Checks the slug rather than the template so that the test stays free of
     * content reads.
     */
    public static function isBucket(Page $page): bool
    {
        return ctype_digit($page->slug()) && strlen($page->slug()) === 4;
    }

    /**
     * Is this page a post?
     *
     * `intendedTemplate()` reads the content *filename*, not the file body,
     * so this is cheap.
     */
    public static function isPost(Page $page): bool
    {
        return in_array($page->intendedTemplate()->name(), self::POST_TEMPLATES, true);
    }

    /**
     * The bucket slug a given time belongs in.
     */
    public static function bucketSlug(\DateTimeInterface $date): string
    {
        return $date->format(option('risto.stream.bucketFormat', 'Y'));
    }

    /**
     * The year bucket a date belongs in, creating it if absent.
     *
     * Buckets are unlisted: they are a storage detail, not navigable content,
     * and keeping them out of ->listed() means nothing accidentally renders
     * them as feed entries.
     */
    public static function bucketFor(\DateTimeInterface $date): Page
    {
        $slug   = self::bucketSlug($date);
        $bucket = page(self::ROOT_ID . '/' . $slug);

        if ($bucket !== null) {
            return $bucket;
        }

        return self::root()->createChild([
            'slug'     => $slug,
            'template' => 'stream-year',
            'content'  => ['title' => $slug],
        ])->changeStatus('unlisted');
    }

    /**
     * The sortable `num` for a given time, as YYYYMMDDHHMMSS.
     *
     * Second precision, so two posts made in the same minute keep a stable,
     * distinct order.
     *
     * This is the ONLY definition of a post's sort key. It is deliberately
     * computed in PHP and stored in the `sortkey` field rather than derived
     * in the blueprint via `num: "{{ page.date.toDate('YmdHis') }}"`, because
     * `toDate()` switches to ICU patterns when `date.handler` is `intl`:
     * there, `YmdHis` silently means year/MINUTE/day/hour and yields
     * 2026452018 instead of 20261120184500. Ordering would break for every
     * new post, with nothing to point at. See docs/PLAN.md §1.3.
     */
    public static function num(\DateTimeInterface|string|int $date): int
    {
        $timestamp = match (true) {
            $date instanceof \DateTimeInterface => $date->getTimestamp(),
            is_int($date)                       => $date,
            default                             => strtotime($date) ?: time(),
        };

        return (int) date('YmdHis', $timestamp);
    }

    /**
     * A `num` that no sibling in this bucket is already using.
     *
     * Non-unique nums silently break the feed: the cursor advances with
     * `num < last`, so every other post sharing that second is skipped and
     * never appears again. Reproduced with three posts made in one second —
     * one vanished permanently.
     *
     * Probes the filesystem rather than $bucket->children(), which would
     * instantiate every sibling just to answer a question the directory
     * listing already contains.
     */
    public static function uniqueNum(Page $bucket, \DateTimeInterface $date): int
    {
        $num  = self::num($date);
        $dir  = $bucket->root();
        $stop = $num + 3600; // an hour of collisions is not a real scenario

        while ($num < $stop && empty(glob($dir . '/' . $num . '_*')) === false) {
            $num++;
        }

        return $num;
    }

    /**
     * Recomputes a post's sort key from its date and re-applies the folder
     * number if they have drifted apart.
     *
     * Needed because the date can be edited in the Panel after creation.
     * Returns true when something actually changed, so the caller (and the
     * update hook) can avoid recursing.
     */
    public static function syncSortkey(Page $page): bool
    {
        $expected = self::num($page->date()->value() ?? 'now');

        $sortkeyMatches = (int) $page->sortkey()->value() === $expected;
        $numMatches     = (int) $page->num() === $expected;

        if ($sortkeyMatches === true && $numMatches === true) {
            return false;
        }

        if ($sortkeyMatches === false) {
            $page = $page->update(['sortkey' => $expected]);
        }

        if ($numMatches === false && $page->isListed() === true) {
            $page->changeStatus('listed');
        }

        return true;
    }
}
