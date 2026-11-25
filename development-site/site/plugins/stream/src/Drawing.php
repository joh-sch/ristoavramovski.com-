<?php

namespace Risto\Stream;

use Kirby\Cms\Page;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;

/**
 * The drawing layer.
 *
 * One global layer, stored as two representations of the same thing:
 *
 *   json  Fabric's own serialisation — the editable source of truth
 *   svg   a flat render, inlined on page load
 *
 * Both are kept because they serve different readers. Visitors get the SVG
 * and never download Fabric (~292KB against a 55KB app bundle); the artist
 * gets the JSON, which round-trips through Fabric without loss. Rendering the
 * JSON client-side instead would mean every visitor paying for the editor.
 */
final class Drawing
{
    public const PAGE_ID = 'drawing';

    /** Fabric JSON can grow quickly; refuse absurd payloads outright. */
    private const MAX_BYTES = 2 * 1024 * 1024;

    public static function page(): Page|null
    {
        return page(self::PAGE_ID);
    }

    /**
     * @return array{json: string|null, svg: string|null, width: int, height: int}
     */
    public static function read(): array
    {
        $page = self::page();

        if ($page === null) {
            return ['json' => null, 'svg' => null, 'width' => 0, 'height' => 0];
        }

        return [
            'json'   => $page->content()->get('json')->isNotEmpty() ? $page->content()->get('json')->value() : null,
            'svg'    => $page->content()->get('svg')->isNotEmpty() ? $page->content()->get('svg')->value() : null,
            'width'  => (int) $page->content()->get('width')->value(),
            'height' => (int) $page->content()->get('height')->value(),
        ];
    }

    /**
     * Replaces the layer and returns what was actually stored.
     *
     * The caller hands the stored SVG back to the client so the live page can
     * update without a reload. Returning it rather than echoing the client's
     * own copy keeps the server the single authority on what gets displayed —
     * the version in the page is then always the sanitised one.
     *
     * @return array{svg: string, width: int, height: int}
     *
     * @throws InvalidArgumentException on malformed or oversized input
     * @throws NotFoundException if the storage page is missing
     */
    public static function write(string $json, string $svg, int $width, int $height): array
    {
        if (strlen($json) > self::MAX_BYTES || strlen($svg) > self::MAX_BYTES) {
            throw new InvalidArgumentException(message: 'That drawing is too large to save.');
        }

        // Must be valid JSON: it is handed straight back to Fabric, and a
        // broken payload would break the editor for good.
        $decoded = json_decode($json, true);

        if (is_array($decoded) === false) {
            throw new InvalidArgumentException(message: 'The drawing data was not valid.');
        }

        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException(message: 'The drawing had no dimensions.');
        }

        $page = self::page();

        if ($page === null) {
            throw new NotFoundException(message: 'Drawing storage page not found.');
        }

        // A canvas with no objects is not an empty drawing to store — it is
        // no drawing at all. Keeping the serialised-but-blank SVG would leave
        // an empty layer in every page, and make "Clear" look like it failed.
        if (empty($decoded['objects'])) {
            $page->update(['json' => '', 'svg' => '', 'width' => 0, 'height' => 0]);

            return ['svg' => '', 'width' => 0, 'height' => 0];
        }

        $clean = self::sanitiseSvg($svg);

        $page->update([
            'json'   => $json,
            'svg'    => $clean,
            'width'  => $width,
            'height' => $height,
        ]);

        return ['svg' => $clean, 'width' => $width, 'height' => $height];
    }

    /**
     * Strips anything executable from the SVG before it is inlined.
     *
     * The SVG comes from the artist's own browser, but it is written into
     * every visitor's page without escaping — so it is treated as untrusted
     * regardless. A compromised or simply buggy client must not be able to
     * turn this into stored XSS.
     */
    public static function sanitiseSvg(string $svg): string
    {
        // Elements that can execute or fetch.
        $svg = preg_replace('~<\s*(script|foreignObject|iframe|object|embed|use)\b[^>]*>.*?<\s*/\s*\1\s*>~is', '', $svg) ?? '';
        $svg = preg_replace('~<\s*(script|foreignObject|iframe|object|embed|use)\b[^>]*/?>~i', '', $svg) ?? '';

        // Inline event handlers: on<anything>="…"
        $svg = preg_replace('~\son[a-z]+\s*=\s*"[^"]*"~i', '', $svg) ?? '';
        $svg = preg_replace("~\\son[a-z]+\\s*=\\s*'[^']*'~i", '', $svg) ?? '';

        // javascript: / data: URLs in href, xlink:href, style
        $svg = preg_replace('~(href|xlink:href)\s*=\s*"(?!#)[^"]*"~i', '', $svg) ?? '';
        $svg = preg_replace('~javascript\s*:~i', '', $svg) ?? '';

        return trim($svg);
    }
}
