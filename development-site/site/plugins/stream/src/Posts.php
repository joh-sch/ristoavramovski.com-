<?php

namespace Risto\Stream;

use DateTimeImmutable;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\Str;
use Throwable;

/**
 * Creating posts.
 *
 * Validation lives here rather than in the route so that the seeder, the
 * Panel and any future import path all go through the same rules.
 */
final class Posts
{
    public const TYPES = ['image', 'text', 'link'];

    private const TEMPLATE_FOR = [
        'image' => 'post-image',
        'text'  => 'post-text',
        'link'  => 'post-link',
    ];

    private const SIZES = ['s', 'm', 'l'];

    /**
     * Creates a post and returns it.
     *
     * @param array{type:string,text?:string,url?:string,size?:string,alt?:string} $input
     * @param array|null $upload A $_FILES-shaped entry, for image posts
     *
     * @throws InvalidArgumentException on any validation failure
     */
    public static function create(array $input, array|null $upload = null): Page
    {
        $type = strtolower(trim((string) ($input['type'] ?? '')));

        if (in_array($type, self::TYPES, true) === false) {
            throw new InvalidArgumentException(message: 'Unknown post type.');
        }

        $size = strtolower(trim((string) ($input['size'] ?? 'm')));
        $size = in_array($size, self::SIZES, true) ? $size : 'm';

        $date   = new DateTimeImmutable();
        $bucket = Stream::bucketFor($date);

        // Must be unique within the bucket: duplicate nums make the feed's
        // cursor skip posts outright. See Stream::uniqueNum().
        $num = Stream::uniqueNum($bucket, $date);

        $content = [
            'date'    => $date->format('Y-m-d H:i:s'),
            'sortkey' => $num,
            'size'    => $size,
        ];

        $text = trim((string) ($input['text'] ?? ''));

        match ($type) {
            'text'  => self::prepareText($content, $text),
            'link'  => self::prepareLink($content, $input, $text),
            'image' => self::prepareImage($content, $text, $upload),
        };

        $post = $bucket->createChild([
            'slug'     => 'post-' . $num,
            'template' => self::TEMPLATE_FOR[$type],
            'content'  => $content,
        ]);

        if ($type === 'image') {
            $post = self::attachImage($post, $upload, (string) ($input['alt'] ?? ''));
        }

        // Listed last: a post should not appear in the feed until its image
        // has actually landed, or a reader can hit a half-built tile.
        return $post->changeStatus('listed');
    }

    // ── per-type validation ─────────────────────────────────────────────────

    private static function prepareText(array &$content, string $text): void
    {
        if ($text === '') {
            throw new InvalidArgumentException(message: 'Please write something first.');
        }

        $content['text']  = $text;
        $content['title'] = Str::excerpt($text, 60, false) ?: 'Note';
    }

    private static function prepareLink(array &$content, array $input, string $text): void
    {
        $url = trim((string) ($input['url'] ?? ''));

        // Be forgiving about a pasted "example.org"; browsers are.
        if ($url !== '' && preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) !== 1) {
            $url = 'https://' . $url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException(message: 'That does not look like a web address.');
        }

        // Only ever link to something a browser will follow as a web page.
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https'], true) === false) {
            throw new InvalidArgumentException(message: 'Only http and https links are supported.');
        }

        $content['url']   = $url;
        $content['text']  = $text;
        $content['title'] = $text !== ''
            ? Str::excerpt($text, 60, false)
            : (string) parse_url($url, PHP_URL_HOST);
    }

    private static function prepareImage(array &$content, string $text, array|null $upload): void
    {
        if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException(message: 'Please choose an image.');
        }

        self::assertUploadOk($upload);

        $content['caption'] = $text;
        $content['title']   = $text !== ''
            ? Str::excerpt($text, 60, false)
            : pathinfo((string) $upload['name'], PATHINFO_FILENAME);
    }

    // ── uploads ─────────────────────────────────────────────────────────────

    /**
     * Rejects an upload before anything touches the filesystem.
     *
     * The extension allowlist is checked against the *real* extension, and the
     * media type against the file's actual contents rather than the
     * browser-supplied Content-Type, which is attacker-controlled.
     */
    private static function assertUploadOk(array $upload): void
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_OK);

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(message: match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That image is too large.',
                UPLOAD_ERR_PARTIAL                        => 'The upload was interrupted.',
                default                                   => 'The upload failed.',
            });
        }

        $maxSize = (int) option('risto.stream.uploads.maxSize', 25 * 1024 * 1024);

        if ((int) ($upload['size'] ?? 0) > $maxSize) {
            throw new InvalidArgumentException(
                message: 'That image is larger than ' . round($maxSize / 1048576) . ' MB.'
            );
        }

        $extension = strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed   = array_map('strtolower', (array) option('risto.stream.uploads.extensions', []));

        if (in_array($extension, $allowed, true) === false) {
            throw new InvalidArgumentException(message: 'That file type is not supported.');
        }

        // Sniff the contents; never trust $upload['type'].
        $mime = @mime_content_type((string) ($upload['tmp_name'] ?? '')) ?: '';

        if (str_starts_with($mime, 'image/') === false) {
            throw new InvalidArgumentException(message: 'That file is not an image.');
        }

        // Catch the case this environment actually has: a HEIC from a phone,
        // on a stack with no HEIC decoder. Storing it would mean a permanently
        // blank tile, so refuse it up front with something actionable.
        if (Images::needsConversion($extension) === true && Images::canDecode($extension) === false) {
            throw new InvalidArgumentException(
                message: 'HEIC images cannot be processed on this server. ' .
                         'Export as JPEG, or enable ImageMagick with HEIC support.'
            );
        }
    }

    private static function attachImage(Page $post, array $upload, string $alt): Page
    {
        try {
            $file = $post->createFile([
                'source'   => $upload['tmp_name'],
                'filename' => self::filename((string) $upload['name']),
                'template' => 'post-image-file',
                'content'  => ['alt' => $alt],
            ]);
        } catch (Throwable $e) {
            // Do not leave an empty post behind.
            $post->delete(true);

            throw new InvalidArgumentException(message: 'The image could not be saved.', previous: $e);
        }

        $display = Images::convert($file);

        if ($display === null) {
            $post->delete(true);

            throw new InvalidArgumentException(message: 'That image could not be converted.');
        }

        Images::pregenerate($display);

        return $post->clone();
    }

    private static function filename(string $original): string
    {
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $name      = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'image';

        return $name . '.' . $extension;
    }

}
