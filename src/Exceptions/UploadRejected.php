<?php

namespace ArtflowStudio\FileUploader\Exceptions;

use RuntimeException;

/**
 * Thrown when a file clears the browser but fails server-side validation.
 *
 * The message is written to be shown to the person who uploaded the file, so
 * it names the file and says what was wrong with it.
 */
class UploadRejected extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $filename = '',
        public readonly string $reason = '',
    ) {
        parent::__construct($message);
    }

    public static function tooLarge(string $filename, float $limitMb): self
    {
        $limit = rtrim(rtrim(number_format($limitMb, 2, '.', ''), '0'), '.');

        return new self(
            "{$filename} is larger than the {$limit}MB limit.",
            $filename,
            'max_size',
        );
    }

    public static function blockedType(string $filename, string $extension): self
    {
        return new self(
            "{$filename} was rejected: .{$extension} files are not allowed.",
            $filename,
            'blocked_extension',
        );
    }

    public static function notAccepted(string $filename, string $mime, string $accept): self
    {
        return new self(
            "{$filename} ({$mime}) does not match the accepted types: {$accept}.",
            $filename,
            'not_accepted',
        );
    }

    public static function unknownDisk(string $disk): self
    {
        return new self(
            "The [{$disk}] disk is not configured. Add it to config/filesystems.php or change af-uploader.disk.",
            '',
            'unknown_disk',
        );
    }
}
