<?php

namespace Risto\Stream;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;

/**
 * Rebuildable, atomically replaced index for mixed post/project pagination.
 */
final class FeedIndex
{
    public const FILE = 'feed-index.json';
    public const DIRTY = 'feed-index.dirty';
    private static int $mutationDepth = 0;

    public static function path(): string
    {
        return kirby()->root('storage') . '/' . self::FILE;
    }

    public static function dirtyPath(): string
    {
        return kirby()->root('storage') . '/' . self::DIRTY;
    }

    public static function lock(): mixed
    {
        $path = kirby()->root('storage') . '/feed-index.lock';
        $handle = fopen($path, 'c');

        if ($handle === false) {
            throw new \RuntimeException(message: 'Could not open the feed index lock.');
        }

        if (flock($handle, LOCK_EX) === false) {
            fclose($handle);
            throw new \RuntimeException(message: 'Could not lock the feed index.');
        }

        return $handle;
    }

    public static function unlock(mixed $handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public static function beginMutation(): void
    {
        self::$mutationDepth++;
    }

    public static function endMutation(): void
    {
        self::$mutationDepth = max(0, self::$mutationDepth - 1);
    }

    public static function markDirty(): void
    {
        $dir = dirname(self::dirtyPath());

        if (is_dir($dir) === false) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(self::dirtyPath(), (string) time(), LOCK_EX);
    }

    public static function rebuild(): array
    {
        if (self::$mutationDepth > 0) {
            self::markDirty();
            return [];
        }

        $handle = self::lock();

        try {
            $rows = [];

            foreach (Stream::buckets() as $bucket) {
                foreach ($bucket->children()->listed()->sortBy('num', 'desc') as $page) {
                    $rows[] = self::row($page);
                }
            }

            if (page(Projects::ROOT_ID) !== null) {
                foreach (Projects::buckets() as $bucket) {
                    foreach ($bucket->children()->listed()->sortBy('num', 'desc') as $page) {
                        if ($page->intendedTemplate()->name() === 'project') {
                            $rows[] = self::row($page);
                        }
                    }
                }
            }

            usort($rows, fn (array $a, array $b) => (int) $b['num'] <=> (int) $a['num']);

            $payload = json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $temp = self::path() . '.tmp-' . bin2hex(random_bytes(6));

            if ($payload === false || file_put_contents($temp, $payload . "\n", LOCK_EX) === false) {
                throw new \RuntimeException(message: 'Could not write the feed index.');
            }

            if (function_exists('fsync')) {
                $stream = fopen($temp, 'rb');
                if (is_resource($stream)) {
                    fsync($stream);
                    fclose($stream);
                }
            }

            if (rename($temp, self::path()) === false) {
                @unlink($temp);
                throw new \RuntimeException(message: 'Could not publish the feed index.');
            }

            @unlink(self::dirtyPath());

            return $rows;
        } finally {
            self::unlock($handle);
        }
    }

    public static function read(): array|null
    {
        if (is_file(self::dirtyPath()) === true || is_file(self::path()) === false) {
            return null;
        }

        $json = @file_get_contents(self::path());

        if ($json === false) {
            return null;
        }

        $rows = json_decode($json, true);

        return is_array($rows) ? $rows : null;
    }

    private static function row(Page $page): array
    {
        return [
            'id'   => $page->id(),
            'uuid' => $page->uuid()?->toString(),
            'num'  => (string) $page->num(),
            'type' => $page->intendedTemplate()->name(),
        ];
    }
}
