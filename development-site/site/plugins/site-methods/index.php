<?php

Kirby::plugin('risto/site-methods', [
    'fileMethods' => [

        /**
         * Prepares an image for the lazy-img snippet.
         *
         * Returns src, optional srcset, a tiny blurred placeholder, the
         * intrinsic ratio and dimensions, and the alt text.
         *
         * Options: width, height, crop, quality, srcset (array of widths)
         */
        'lazyImg' => function (array $options = []): array {
            $maxWidth = 1500;

            $width  = $this->width();
            $height = $this->height();
            $ratio  = $width && $height ? $width . '/' . $height : null;
            $alt    = $this->alt()->value() ?? '';

            // SVGs and other non-resizable files are served as-is.
            if ($this->isResizable() === false) {
                return [
                    'src'         => $this->url(),
                    'srcset'      => null,
                    'placeholder' => null,
                    'ratio'       => $ratio,
                    'width'       => $width,
                    'height'      => $height,
                    'alt'         => $alt,
                ];
            }

            $thumbOptions = array_filter([
                'width'   => isset($options['width'])  ? min((int) $options['width'],  $maxWidth) : null,
                'height'  => isset($options['height']) ? min((int) $options['height'], $maxWidth) : null,
                'crop'    => $options['crop']    ?? null,
                'quality' => $options['quality'] ?? null,
            ], fn ($value) => $value !== null);

            $thumb = $thumbOptions ? $this->thumb($thumbOptions) : $this;

            return [
                'src'    => $thumb->url(),
                'srcset' => isset($options['srcset'])
                    ? $this->srcset(array_map(
                        fn ($size) => min((int) $size, $maxWidth),
                        $options['srcset']
                    ))
                    : null,
                'placeholder' => $this->thumb([
                    'width'   => 24,
                    'blur'    => 4,
                    'quality' => 40,
                ])->url(),
                'ratio'  => $ratio,
                'width'  => $thumb->width(),
                'height' => $thumb->height(),
                'alt'    => $alt,
            ];
        },
    ],
]);
