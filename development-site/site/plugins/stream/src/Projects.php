<?php

namespace Risto\Stream;

use DateTimeImmutable;
use DateTimeInterface;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Toolkit\Str;
use Throwable;

final class Projects
{
    public const ROOT_ID = 'projects';
    public const MAX_LAYERS = 12;
    public const MAX_TEXT = 5000;
    private static bool $syncing = false;

    public static function root(): Page
    {
        $root = page(self::ROOT_ID);

        if ($root === null) {
            throw new NotFoundException(message: 'Projects root "projects" was not found.');
        }

        return $root;
    }

    public static function buckets(): Pages
    {
        return self::root()
            ->children()
            ->filter(fn (Page $page) => ctype_digit($page->slug()) && strlen($page->slug()) === 4)
            ->sortBy('slug', 'desc');
    }

    public static function bucketFor(DateTimeInterface $date): Page
    {
        $slug = $date->format(option('risto.stream.bucketFormat', 'Y'));
        $bucket = page(self::ROOT_ID . '/' . $slug);

        if ($bucket !== null) {
            return $bucket;
        }

        return self::root()->createChild([
            'slug' => $slug,
            'template' => 'project-year',
            'content' => ['title' => $slug],
        ])->changeStatus('unlisted');
    }

    public static function bucketForNum(int $num): Page
    {
        $date = DateTimeImmutable::createFromFormat('YmdHis', (string) $num);

        if ($date === false) {
            throw new InvalidArgumentException(message: 'Invalid project sort key.');
        }

        return self::bucketFor($date);
    }

    public static function assertUniqueSlug(string $slug, Page|null $except = null): string
    {
        $base = Str::slug($slug) ?: 'project';
        $candidate = $base;
        $index = 2;

        foreach (self::buckets() as $bucket) {
            foreach ($bucket->childrenAndDrafts() as $project) {
                if ($except !== null && $project->is($except) === true) {
                    continue;
                }

                if ($project->slug() === $candidate) {
                    $candidate = $base . '-' . $index++;
                }
            }
        }

        return $candidate;
    }

    public static function create(array $input, array $layers): Page
    {
        $title = trim((string) ($input['title'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException(message: 'Please give the project a title.');
        }

        if (mb_strlen($title) > 120) {
            throw new InvalidArgumentException(message: 'The project title is too long.');
        }

        if (count($layers) < 1 || count($layers) > self::MAX_LAYERS) {
            throw new InvalidArgumentException(message: 'Projects need between 1 and 12 layers.');
        }

        $size = strtolower(trim((string) ($input['size'] ?? 'm')));
        $size = in_array($size, ['s', 'm', 'l'], true) ? $size : 'm';
        $requestId = trim((string) ($input['requestId'] ?? ''));

        if ($requestId === '') {
            throw new InvalidArgumentException(message: 'Missing project request ID.');
        }

        $existing = self::findByRequestId($requestId);
        if ($existing !== null) {
            return $existing;
        }

        try {
            $date = new DateTimeImmutable();
            $num = self::uniqueNum($date);
            $bucket = self::bucketForNum($num);
            $slug = self::assertUniqueSlug($title);

            $project = $bucket->createChild([
                'slug' => $slug,
                'template' => 'project',
                'content' => [
                    'title' => $title,
                    'date' => $date->format('Y-m-d H:i:s'),
                    'sortkey' => (string) $num,
                    'size' => $size,
                    'requestId' => $requestId,
                    'layers' => [],
                ],
            ]);

            $structure = [];

            foreach ($layers as $index => $layer) {
                $type = strtolower(trim((string) ($layer['type'] ?? '')));

                if ($type === 'text') {
                    $text = trim((string) ($layer['text'] ?? ''));

                    if ($text === '') {
                        throw new InvalidArgumentException(message: 'A text layer cannot be empty.');
                    }

                    if (mb_strlen($text) > self::MAX_TEXT) {
                        throw new InvalidArgumentException(message: 'A text layer is too long.');
                    }

                    $structure[] = ['type' => 'text', 'text' => $text];
                    continue;
                }

                if ($type !== 'image') {
                    throw new InvalidArgumentException(message: 'Unknown project layer type.');
                }

                $upload = $layer['upload'] ?? null;

                if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    throw new InvalidArgumentException(message: 'An image layer has no image.');
                }

                if (!method_exists(Posts::class, 'assertUploadOk')) {
                    throw new InvalidArgumentException(message: 'Upload validation is unavailable.');
                }

                Posts::assertUploadOk($upload);

                $alt = trim((string) ($layer['alt'] ?? ''));
                if ($alt === '') {
                    throw new InvalidArgumentException(message: 'Please add alt text to every project image.');
                }

                $file = $project->createFile([
                    'source' => $upload['tmp_name'],
                    'filename' => self::filename((string) $upload['name'], $index),
                    'template' => 'project-image-file',
                    'content' => ['alt' => $alt],
                ]);

                $display = Images::convert($file);

                if ($display === null) {
                    throw new InvalidArgumentException(message: 'A project image could not be converted.');
                }

                Images::pregenerate($display);

                $structure[] = [
                    'type' => 'image',
                    'file' => $display->uuid()?->toString() ?? $display->id(),
                ];
            }

            $project = $project->update(['layers' => $structure]);
            $project = $project->changeStatus('listed', $num);

            return $project;
        } catch (Throwable $e) {
            if (isset($project) && $project->exists() === true) {
                $project->delete(true);
            }

            if ($e instanceof InvalidArgumentException) {
                throw $e;
            }

            throw new InvalidArgumentException(
                message: 'The project could not be saved: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public static function findByRequestId(string $requestId): Page|null
    {
        foreach (self::buckets() as $bucket) {
            foreach ($bucket->childrenAndDrafts() as $project) {
                if ($project->intendedTemplate()->name() === 'project'
                    && (string) $project->requestId()->value() === $requestId
                ) {
                    return $project;
                }
            }
        }

        return null;
    }

    public static function syncSortkey(Page $project): bool
    {
        if (self::$syncing === true
            || $project->intendedTemplate()->name() !== 'project'
        ) {
            return false;
        }

        self::$syncing = true;

        try {
            $dateValue = (string) ($project->date()->value() ?? 'now');
            $date = new DateTimeImmutable($dateValue);
            $num = self::uniqueNum($date, $project);
            $changed = false;

            if ((int) $project->sortkey()->value() !== $num) {
                $project = $project->update(['sortkey' => (string) $num]);
                $changed = true;
            }

            $bucket = self::bucketForNum($num);

            if ($project->parent()->is($bucket) === false) {
                $project = $project->move($bucket);
                $changed = true;
            }

            if ($changed === true && $project->isListed() === true) {
                $project = $project->changeStatus('listed', $num);
            }

            return $changed;
        } finally {
            self::$syncing = false;
        }
    }

    public static function delete(Page $project): void
    {
        if ($project->intendedTemplate()->name() !== 'project') {
            throw new InvalidArgumentException(message: 'That page is not a project.');
        }

        $project->delete(true);
    }

    public static function uniqueNum(DateTimeInterface $date, Page|null $except = null): int
    {
        $lock = FeedIndex::lock();

        try {
            $num = Stream::num($date);

            for ($attempt = 0; $attempt < 3600; $attempt++, $num++) {
                $occupied = false;

                foreach ([Stream::root(), self::root()] as $root) {
                    foreach (glob($root->root() . '/*/*/' . $num . '_*') ?: [] as $path) {
                        $belongsToExcept = $except !== null
                            && str_contains($path, '/' . $num . '_' . $except->slug());

                        if ($path !== '' && $belongsToExcept === false) {
                            $occupied = true;
                            break 2;
                        }
                    }
                }

                if ($occupied === false) {
                    return $num;
                }
            }

            throw new InvalidArgumentException(message: 'Could not allocate a project position.');
        } finally {
            FeedIndex::unlock($lock);
        }
    }

    private static function filename(string $original, int $index): string
    {
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $name = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: ('project-' . ($index + 1));

        return $name . '.' . $extension;
    }
}
