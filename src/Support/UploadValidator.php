<?php

namespace ArtflowStudio\FileUploader\Support;

use ArtflowStudio\FileUploader\Exceptions\UploadRejected;
use Illuminate\Http\UploadedFile;

/**
 * Server-side enforcement.
 *
 * The browser-side accept/max-size checks are a convenience — anyone can POST
 * straight at Livewire's upload endpoint and skip them entirely. Nothing is
 * trusted here except the bytes on disk.
 */
final class UploadValidator
{
    /**
     * @param  string|null  $accept  The component's accept expression, e.g. "image/*,.pdf".
     * @param  float|null  $maxSizeMb  Component ceiling; the config ceiling still applies on top.
     *
     * @throws UploadRejected
     */
    public static function assertAllowed(UploadedFile $file, ?string $accept = null, ?float $maxSizeMb = null): void
    {
        $config = (array) config('af-uploader.validation', []);

        if (! ($config['enforce'] ?? true)) {
            return;
        }

        self::assertSize($file, $maxSizeMb, $config);
        self::assertExtension($file, $config);
        self::assertAccepted($file, $accept);
    }

    /**
     * True when the file matches an HTML accept expression.
     *
     * Handles the three forms the attribute allows: a wildcard family
     * ("image/*"), a concrete MIME type ("application/pdf") and an extension
     * (".webp"). An empty expression accepts everything.
     */
    public static function matchesAccept(UploadedFile $file, ?string $accept): bool
    {
        $accept = trim((string) $accept);

        if ($accept === '' || $accept === '*' || $accept === '*/*') {
            return true;
        }

        $mime = strtolower((string) $file->getMimeType());
        $extension = strtolower((string) $file->getClientOriginalExtension());

        foreach (explode(',', $accept) as $token) {
            $token = strtolower(trim($token));

            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '.')) {
                if ($extension === ltrim($token, '.')) {
                    return true;
                }

                continue;
            }

            if (str_ends_with($token, '/*')) {
                if (str_starts_with($mime, rtrim($token, '*'))) {
                    return true;
                }

                continue;
            }

            if ($mime === $token) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws UploadRejected
     */
    private static function assertSize(UploadedFile $file, ?float $maxSizeMb, array $config): void
    {
        $ceiling = $config['max_size'] ?? null;

        $limit = match (true) {
            $ceiling !== null && $maxSizeMb !== null => min((float) $ceiling, $maxSizeMb),
            $ceiling !== null => (float) $ceiling,
            default => $maxSizeMb,
        };

        if ($limit === null || $limit <= 0.0) {
            return;
        }

        $bytes = (int) round($limit * 1024 * 1024);

        if ($file->getSize() > $bytes) {
            throw UploadRejected::tooLarge($file->getClientOriginalName(), $limit);
        }
    }

    /**
     * Reject executables by both the claimed extension and the extension
     * implied by the real MIME type, so renaming shell.php to shell.png does
     * not get past it.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws UploadRejected
     */
    private static function assertExtension(UploadedFile $file, array $config): void
    {
        $blocked = array_map('strtolower', (array) ($config['blocked_extensions'] ?? []));

        if (! ($config['allow_svg'] ?? false)) {
            $blocked[] = 'svg';
            $blocked[] = 'svgz';
        }

        if ($blocked === []) {
            return;
        }

        $candidates = array_filter([
            strtolower((string) $file->getClientOriginalExtension()),
            strtolower((string) $file->guessExtension()),
        ]);

        foreach ($candidates as $extension) {
            if (in_array($extension, $blocked, true)) {
                throw UploadRejected::blockedType($file->getClientOriginalName(), $extension);
            }
        }

        // A double extension (avatar.php.png) still executes under some
        // misconfigured web servers, so treat every segment as a candidate.
        $segments = array_slice(explode('.', strtolower($file->getClientOriginalName())), 1);

        foreach ($segments as $segment) {
            if (in_array($segment, $blocked, true)) {
                throw UploadRejected::blockedType($file->getClientOriginalName(), $segment);
            }
        }
    }

    /**
     * @throws UploadRejected
     */
    private static function assertAccepted(UploadedFile $file, ?string $accept): void
    {
        if ($accept === null || self::matchesAccept($file, $accept)) {
            return;
        }

        throw UploadRejected::notAccepted(
            $file->getClientOriginalName(),
            (string) $file->getMimeType(),
            $accept
        );
    }
}
