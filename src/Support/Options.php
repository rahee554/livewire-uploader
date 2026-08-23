<?php

namespace ArtflowStudio\FileUploader\Support;

/**
 * The single place where package config and component props are merged,
 * normalised and cast.
 *
 * Everything the browser needs leaves here as one JSON blob written onto the
 * root element. The old build scattered the same settings across an Alpine
 * config object *and* a parallel set of data-af-* attributes, which drifted out
 * of sync in both directions (AUDIT.md, HIGH-05) — three documented features
 * were unreachable as a result.
 */
final class Options
{
    /**
     * @param  array<string, mixed>  $props  Raw prop values straight off the Blade tag.
     * @return array<string, mixed>
     */
    public static function build(string $id, ?string $model, array $props): array
    {
        $config = (array) config('af-uploader', []);
        $defaults = (array) ($config['defaults'] ?? []);
        $image = (array) ($config['image'] ?? []);
        $editor = (array) ($config['editor'] ?? []);

        $editorEnabled = self::bool(
            self::pick($props, ['cropper', 'editor']),
            (bool) ($editor['enabled'] ?? false)
        );

        $ratio = self::ratio(self::pick($props, ['ratio']) ?? ($editor['ratio'] ?? null));
        $circle = self::bool(self::pick($props, ['isCircle', 'circle']), (bool) ($editor['circle'] ?? false));

        // A circular mask is only coherent at 1:1, so pin the ratio rather than
        // letting the user pick a rectangle and get an ellipse.
        if ($circle) {
            $ratio = 1.0;
        }

        $lossless = self::bool(self::pick($props, ['lossless']), (bool) ($image['lossless'] ?? false));
        $convert = self::format(self::pick($props, ['convert']) ?? ($image['convert'] ?? null));

        return [
            'id' => $id,
            'model' => $model,

            // Page-wide, but carried on every instance so the first uploader
            // to initialise can start theme syncing without extra plumbing.
            'theme' => self::theme(self::pick($props, ['theme']) ?? $config['theme'] ?? 'auto'),
            'multiple' => self::bool(self::pick($props, ['multiple']), false),
            'accept' => (string) (self::pick($props, ['accept']) ?? $defaults['accept'] ?? 'image/*'),
            'maxSize' => self::float(self::pick($props, ['maxSize']) ?? ($defaults['max_size'] ?? 10)),
            'maxFiles' => self::intOrNull(self::pick($props, ['maxFiles']) ?? ($defaults['max_files'] ?? null)),
            'autoUpload' => self::bool(self::pick($props, ['autoUpload']), (bool) ($defaults['auto_upload'] ?? true)),
            'preview' => self::bool(self::pick($props, ['preview']), (bool) ($defaults['preview'] ?? true)),

            // Opt-in: report what the client-side re-encode saved. Off by
            // default so an ordinary uploader stays a dropzone, not a report.
            'showSavings' => self::bool(
                self::pick($props, ['showSavings']),
                (bool) ($defaults['show_savings'] ?? false)
            ),

            'image' => [
                'convert' => $convert,
                'quality' => $lossless ? 1.0 : self::quality(
                    self::pick($props, ['quality']) ?? ($image['quality'] ?? 0.82)
                ),
                'lossless' => $lossless,
                'maxWidth' => self::intOrNull(self::pick($props, ['maxWidth']) ?? ($image['max_width'] ?? null)),
                'maxHeight' => self::intOrNull(self::pick($props, ['maxHeight']) ?? ($image['max_height'] ?? null)),
                // Two independent ceilings on the encoded output. Either may be
                // given; when both are, the tighter one wins.
                //
                //   target-size="500KB"  an absolute budget
                //   compress="60%"       at most 60% of whatever was picked
                //
                // The percentage can only be resolved in the browser, which is
                // the only place the original byte count is known.
                'targetSize' => self::bytes(
                    self::pick($props, ['targetSize']) ?? ($image['target_size'] ?? null)
                ),
                'compress' => self::percent(
                    self::pick($props, ['compress']) ?? ($image['compress'] ?? null)
                ),
            ],

            'editor' => [
                'enabled' => $editorEnabled,
                'ratio' => $ratio,
                'circle' => $circle,
                'lockRatio' => self::bool(self::pick($props, ['lockRatio']), (bool) ($editor['lock_ratio'] ?? false))
                    || $circle,
                'format' => $convert
                    ? 'image/'.$convert
                    : (string) ($editor['format'] ?? 'image/webp'),
            ],
        ];
    }

    /**
     * Parse a human file size into bytes. Accepts '500KB', '1.5 mb', '400kb',
     * a bare number (treated as bytes) or null.
     */
    public static function bytes(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value > 0 ? (int) $value : null;
        }

        if (! preg_match('/^\s*([\d.]+)\s*(b|kb|mb|gb)?\s*$/i', (string) $value, $m)) {
            return null;
        }

        $factor = match (strtolower($m[2] ?? 'b')) {
            'kb' => 1024,
            'mb' => 1024 ** 2,
            'gb' => 1024 ** 3,
            default => 1,
        };

        $bytes = (int) round(((float) $m[1]) * $factor);

        return $bytes > 0 ? $bytes : null;
    }

    /**
     * Normalise the theme mode, falling back to following the host app.
     */
    public static function theme(mixed $value): string
    {
        $mode = strtolower(trim((string) $value));

        return in_array($mode, ['auto', 'system', 'light', 'dark'], true) ? $mode : 'auto';
    }

    /**
     * Parse a compression percentage: how much of the original size the output
     * is allowed to keep.
     *
     * Accepts '60%', '60', or 0.6 — all meaning "at most 60% of the original".
     * Returns null when nothing usable was given, and refuses 100 or more,
     * which would not be a compression instruction at all.
     */
    public static function percent(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if (! preg_match('/^\s*([\d.]+)\s*%?\s*$/', (string) $value, $m)) {
            return null;
        }

        $number = (float) $m[1];

        // A fraction is the same instruction written differently.
        if ($number > 0 && $number <= 1 && str_contains((string) $value, '.')) {
            $number *= 100;
        }

        $percent = (int) round($number);

        return $percent >= 1 && $percent < 100 ? $percent : null;
    }

    /**
     * Normalise an aspect ratio to a float. Accepts '16/9', '16:9', '1.777',
     * 1, 'free' / '0' (free-form) or null.
     */
    public static function ratio(mixed $value): ?float
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if (is_string($value) && in_array(strtolower(trim($value)), ['free', 'none', '0'], true)) {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value > 0 ? (float) $value : 0.0;
        }

        if (preg_match('#^\s*([\d.]+)\s*[/:]\s*([\d.]+)\s*$#', (string) $value, $m)) {
            $w = (float) $m[1];
            $h = (float) $m[2];

            return $h > 0.0 ? $w / $h : null;
        }

        return null;
    }

    /**
     * Accept the string 'false' as false.
     *
     * Blade props arrive as strings unless the caller uses :prop="...", so the
     * old code's `$autoUpload ? 'true' : 'false'` evaluated the string 'false'
     * as truthy and the prop never worked at all (AUDIT.md, CRIT-01).
     */
    public static function bool(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalised = strtolower(trim($value));

            if (in_array($normalised, ['false', '0', 'no', 'off', 'null'], true)) {
                return false;
            }

            if (in_array($normalised, ['true', '1', 'yes', 'on'], true)) {
                return true;
            }

            // A bare attribute (<x-af-uploader multiple />) arrives as the
            // attribute's own name.
            return true;
        }

        return (bool) $value;
    }

    private static function format(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        $format = strtolower(trim((string) $value));
        $format = str_replace(['image/', 'jpg'], ['', 'jpeg'], $format);

        return in_array($format, ['webp', 'jpeg', 'png'], true) ? $format : null;
    }

    private static function quality(mixed $value): float
    {
        $quality = (float) $value;

        // Tolerate 0-100 as well as 0-1.
        if ($quality > 1.0) {
            $quality /= 100.0;
        }

        return max(0.05, min(1.0, $quality));
    }

    private static function float(mixed $value): float
    {
        return max(0.0, (float) $value);
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        return (int) $value > 0 ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  list<string>  $keys
     */
    private static function pick(array $props, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $props) && $props[$key] !== null) {
                return $props[$key];
            }
        }

        return null;
    }
}
