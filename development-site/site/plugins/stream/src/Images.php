<?php

namespace Risto\Stream;

use Imagick;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Filesystem\F;
use Throwable;

/**
 * What the installed image stack can actually do, and the conversions the
 * stream needs on upload.
 *
 * Capabilities are detected rather than assumed, because they vary wildly
 * between the machine a site is developed on and the one it runs on. Measured
 * on this project alone:
 *
 *   Homebrew PHP 8.4 (CLI)   GD, AVIF write yes
 *   MAMP PHP 8.5.2 (web)     GD, AVIF write NO
 *   MAMP bundled imagick     ImageMagick 7.1.0-23, built WITHOUT heic/heif/avif
 *
 * Hardcoding `avif` would therefore produce broken thumbnails on the very
 * machine this is developed on.
 */
final class Images
{
    /** Formats we are willing to emit, best first. */
    private const PREFERRED = ['avif', 'webp', 'jpg'];

    private static array $cache = [];

    /**
     * The best thumbnail format this installation can actually write.
     */
    public static function thumbFormat(): string
    {
        return self::$cache['thumbFormat'] ??= (function (): string {
            foreach (self::PREFERRED as $format) {
                if (self::canEncode($format) === true) {
                    return $format;
                }
            }

            // jpg is always available with either driver.
            return 'jpg';
        })();
    }

    /**
     * Can the installed driver write this format?
     */
    public static function canEncode(string $format): bool
    {
        $format = strtolower($format);

        return self::$cache['encode'][$format] ??= match (true) {
            self::hasImagickFormat($format) => true,

            // GD exposes imageavif()/imagewebp() even in builds that cannot
            // actually encode, so gd_info() is the only trustworthy check.
            $format === 'avif' => (self::gdInfo()['AVIF Support'] ?? false) === true,
            $format === 'webp' => (self::gdInfo()['WebP Support'] ?? false) === true,
            $format === 'jpg', $format === 'jpeg' => (self::gdInfo()['JPEG Support'] ?? false) === true,

            default => false,
        };
    }

    /**
     * Can the installed driver *read* this format?
     *
     * HEIC is the one that matters: iPhones produce it, no browser renders it,
     * and Kirby excludes it from Image::$resizableTypes, so it cannot be
     * rescued later by the thumb pipeline.
     */
    public static function canDecode(string $extension): bool
    {
        $extension = strtolower($extension);

        return self::$cache['decode'][$extension] ??= match ($extension) {
            'heic', 'heif' => self::hasImagickFormat($extension),
            'avif'         => self::hasImagickFormat('avif') || (self::gdInfo()['AVIF Support'] ?? false) === true,
            'webp'         => self::hasImagickFormat('webp') || (self::gdInfo()['WebP Support'] ?? false) === true,
            'jpg', 'jpeg', 'png', 'gif' => true,
            default        => false,
        };
    }

    /**
     * Extensions that need converting before they can be displayed at all.
     */
    public static function needsConversion(string $extension): bool
    {
        $from = array_map('strtolower', (array) option('risto.stream.convert.from', ['heic', 'heif']));

        return in_array(strtolower($extension), $from, true);
    }

    /**
     * A human-readable summary, for diagnostics and the upload error message.
     *
     * @return array<string,mixed>
     */
    public static function report(): array
    {
        return [
            'driver'      => extension_loaded('imagick') ? 'imagick' : 'gd',
            'thumbFormat' => self::thumbFormat(),
            'canReadHeic' => self::canDecode('heic'),
            'canWriteAvif' => self::canEncode('avif'),
            'canWriteWebp' => self::canEncode('webp'),
        ];
    }

    /**
     * Converts an upload the browser cannot display into one it can.
     *
     * Only HEIC/HEIF reach here. The original is kept (it is the artist's
     * archive, and a format decision made at upload time should not destroy
     * it) but re-templated so the feed never picks it up.
     *
     * Returns the file to display, or null when conversion is impossible —
     * the caller must treat null as a rejected upload rather than storing an
     * image no browser can render.
     */
    public static function convert(File $file): File|null
    {
        if (self::needsConversion($file->extension()) === false) {
            return $file;
        }

        if (self::canDecode($file->extension()) === false) {
            return null;
        }

        $target = strtolower((string) option('risto.stream.convert.to', 'avif'));

        // Fall back rather than fail: a machine that can read HEIC but not
        // write AVIF should still end up with something displayable.
        if (self::canEncode($target) === false) {
            $target = self::thumbFormat();
        }

        $quality  = (int) option('risto.stream.convert.quality', 82);
        $basename = pathinfo($file->filename(), PATHINFO_FILENAME);
        $tmp      = sys_get_temp_dir() . '/risto-' . $basename . '-' . bin2hex(random_bytes(4)) . '.' . $target;

        try {
            $image = new Imagick($file->root());

            // Phone photos carry their rotation in EXIF rather than in the
            // pixels. Bake it in here, because the EXIF is stripped below.
            $image = self::applyOrientation($image);
            $image->setImageFormat($target);
            $image->setImageCompressionQuality($quality);

            // Drops GPS coordinates along with everything else.
            $image->stripImage();
            $image->writeImage($tmp);
            $image->clear();
        } catch (Throwable) {
            F::remove($tmp);

            return null;
        }

        $page = $file->parent();

        if ($page instanceof Page === false) {
            F::remove($tmp);

            return null;
        }

        try {
            $converted = $page->createFile([
                'source'   => $tmp,
                'filename' => $basename . '.' . $target,
                'template' => 'post-image-file',
                'content'  => ['alt' => $file->alt()->value() ?? ''],
            ]);
        } catch (Throwable) {
            F::remove($tmp);

            return null;
        }

        F::remove($tmp);

        // Keep the untouched upload, out of the feed's way.
        try {
            $file->changeTemplate('post-image-original');
        } catch (Throwable) {
            // Not fatal: the converted file is what gets displayed either way.
        }

        return $converted;
    }

    /**
     * Builds the thumbnails the feed will ask for, at upload time.
     *
     * Without this the first visitor after a post pays for every thumbnail
     * generation. Measured at ~113ms per AVIF thumb and ~63ms per WebP.
     */
    public static function pregenerate(File $file): void
    {
        if ($file->isResizable() === false) {
            return;
        }

        $widths = (array) option('risto.stream.tileWidths', [400, 800, 1200, 1600]);

        foreach ($widths as $width) {
            $width = (int) $width;

            // Never upscale.
            if ($width <= 0 || $width > $file->width()) {
                continue;
            }

            try {
                // save() is what writes the file. thumb() alone only returns
                // a lazy FileVersion, so without it nothing is pre-generated
                // and the first visitor still pays for every size.
                $file->thumb(['width' => $width])->save();
            } catch (Throwable) {
                // A single failed size must not take the whole upload down.
            }
        }

        // Must match lazyImg()'s placeholder options exactly, or it misses.
        try {
            $file->thumb(['width' => 24, 'blur' => 4, 'quality' => 40])->save();
        } catch (Throwable) {
        }
    }

    // ── internals ───────────────────────────────────────────────────────────

    /**
     * Bakes EXIF orientation into the pixels.
     *
     * Kirby's own thumb drivers auto-orient, but stripImage() below removes
     * the EXIF that tells them to, so it has to happen here first.
     */
    private static function applyOrientation(Imagick $image): Imagick
    {
        $orientation = $image->getImageOrientation();

        match ($orientation) {
            Imagick::ORIENTATION_TOPRIGHT    => $image->flopImage(),
            Imagick::ORIENTATION_BOTTOMRIGHT => $image->rotateImage('#000', 180),
            Imagick::ORIENTATION_BOTTOMLEFT  => $image->flipImage(),
            Imagick::ORIENTATION_LEFTTOP     => ($image->flopImage() && $image->rotateImage('#000', -90)),
            Imagick::ORIENTATION_RIGHTTOP    => $image->rotateImage('#000', 90),
            Imagick::ORIENTATION_RIGHTBOTTOM => ($image->flopImage() && $image->rotateImage('#000', 90)),
            Imagick::ORIENTATION_LEFTBOTTOM  => $image->rotateImage('#000', -90),
            default                          => null,
        };

        $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

        return $image;
    }

    private static function hasImagickFormat(string $format): bool
    {
        if (extension_loaded('imagick') === false) {
            return false;
        }

        self::$cache['imagickFormats'] ??= (function (): array {
            try {
                return array_map('strtolower', Imagick::queryFormats());
            } catch (Throwable) {
                return [];
            }
        })();

        $format = strtolower($format);
        $format = $format === 'jpg' ? 'jpeg' : $format;

        return in_array($format, self::$cache['imagickFormats'], true);
    }

    private static function gdInfo(): array
    {
        return self::$cache['gdInfo'] ??= function_exists('gd_info') ? gd_info() : [];
    }
}
