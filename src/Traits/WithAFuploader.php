<?php

namespace ArtflowStudio\FileUploader\Traits;

use ArtflowStudio\FileUploader\Concerns\WithUploader;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Backwards-compatible alias for {@see WithUploader}.
 *
 * @deprecated Use ArtflowStudio\FileUploader\Concerns\WithUploader.
 *
 * The filename is deliberately WithAFuploader.php, matching the spelling
 * existing consumers already import. PHP resolves trait names
 * case-insensitively but Composer resolves *paths* case-sensitively, so a file
 * named WithAFUploader.php broke `use ...\Traits\WithAFuploader` on Linux
 * (AUDIT.md, CRIT-05). This file is also listed under autoload.files, so it is
 * declared eagerly and PSR-4 path resolution never runs against either
 * spelling.
 */
trait WithAFuploader
{
    use WithUploader;

    /** @deprecated Use storeUpload(). */
    public function storeAFUpload(
        TemporaryUploadedFile $file,
        string $path,
        string $disk = 'public',
        ?string $name = null
    ): string {
        return $this->storeUpload($file, $path, $disk, $name);
    }

    /** @deprecated Use discardUpload(). */
    public function revertUpload(string $property, ?string $filename = null): void
    {
        $this->discardUpload($property, $filename);
    }

    /** @deprecated Use deleteStoredUpload(). */
    public function removeUpload(string $property, string $filename): void
    {
        $this->deleteStoredUpload($property, $filename);
    }

    /** @deprecated The uploader reports its own status now. */
    public function dispatchUploadSuccess(string $inputId): void
    {
        $this->dispatch('af-upload-success', inputId: $inputId);
    }
}
