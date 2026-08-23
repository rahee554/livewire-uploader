<?php

namespace ArtflowStudio\FileUploader\Concerns;

use ArtflowStudio\FileUploader\Exceptions\UploadRejected;
use ArtflowStudio\FileUploader\Support\UploadValidator;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Server-side half of <x-af-uploader>.
 *
 * Adds three things on top of Livewire's WithFileUploads:
 *
 *  1. Temporary files are deleted along with their sidecar .json metadata.
 *     Livewire writes both but only ever removes the first, so livewire-tmp
 *     grows forever in long-lived apps.
 *  2. discardUpload() is callable from the browser, so clearing the dropzone
 *     actually releases the temporary file instead of orphaning it.
 *  3. storeUpload() validates before it writes, and reports failures back to
 *     the component through the same channel the JS already listens on.
 */
trait WithUploader
{
    use WithFileUploads;

    /**
     * Move a temporary upload into permanent storage.
     *
     * Prefer this over calling $file->store() directly — it validates first,
     * and it cleans up both halves of the temporary file afterwards.
     *
     * @param  string|null  $accept  Mirror of the component's accept expression.
     * @return string The path relative to the disk root.
     *
     * @throws UploadRejected
     */
    public function storeUpload(
        TemporaryUploadedFile $file,
        ?string $directory = null,
        ?string $disk = null,
        ?string $name = null,
        ?string $accept = null,
        ?float $maxSize = null,
    ): string {
        $disk ??= (string) config('af-uploader.disk', 'public');
        $directory ??= (string) config('af-uploader.directory', 'uploads');

        if (config("filesystems.disks.{$disk}") === null) {
            throw UploadRejected::unknownDisk($disk);
        }

        UploadValidator::assertAllowed($file, $accept, $maxSize);

        $path = $name
            ? $file->storeAs($directory, $name, $disk)
            : $file->store($directory, $disk);

        $this->purgeTemporaryFile($file);

        return (string) $path;
    }

    /**
     * Store every temporary file held by an array property.
     *
     * Files that fail validation are skipped rather than aborting the batch;
     * their messages come back in the second element so the caller can show
     * them all at once.
     *
     * @param  iterable<TemporaryUploadedFile>  $files
     * @return array{0: list<string>, 1: list<string>} [stored paths, rejection messages]
     */
    public function storeUploads(
        iterable $files,
        ?string $directory = null,
        ?string $disk = null,
        ?string $accept = null,
        ?float $maxSize = null,
    ): array {
        $stored = [];
        $rejected = [];

        foreach ($files as $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }

            try {
                $stored[] = $this->storeUpload($file, $directory, $disk, null, $accept, $maxSize);
            } catch (UploadRejected $e) {
                $rejected[] = $e->getMessage();
            }
        }

        return [$stored, $rejected];
    }

    /**
     * Drop a pending upload and release its temporary file.
     *
     * Called from the browser when the clear button is pressed. Without it the
     * property is nulled but the bytes stay in livewire-tmp until the janitor
     * command runs — if it ever does.
     *
     * @param  string|null  $filename  Removes just this file from an array property.
     */
    public function discardUpload(string $property, ?string $filename = null): void
    {
        if (! $this->hasProperty($property)) {
            return;
        }

        $value = $this->getPropertyValue($property);

        if ($value instanceof TemporaryUploadedFile) {
            $this->purgeTemporaryFile($value);
            $this->{$property} = null;

            return;
        }

        if (! is_array($value)) {
            $this->{$property} = null;

            return;
        }

        $kept = [];

        foreach ($value as $item) {
            if (! $item instanceof TemporaryUploadedFile) {
                continue;
            }

            if ($filename === null || $item->getFilename() === $filename) {
                $this->purgeTemporaryFile($item);

                continue;
            }

            $kept[] = $item;
        }

        $this->{$property} = $kept;
    }

    /**
     * Delete a file that has already been written to permanent storage, and
     * drop it from the bound property.
     *
     * $path must be a path relative to the disk root — the value storeUpload()
     * returned. Absolute paths, URLs and anything containing a traversal
     * segment are refused, because this method is reachable from the browser.
     */
    public function deleteStoredUpload(string $property, string $path, ?string $disk = null): bool
    {
        $disk ??= (string) config('af-uploader.disk', 'public');

        $path = $this->normalizeStoredPath($path);

        if ($path === null) {
            return false;
        }

        if ($this->hasProperty($property)) {
            $value = $this->getPropertyValue($property);

            $this->{$property} = is_array($value)
                ? array_values(array_filter($value, fn ($item) => $item !== $path))
                : null;
        }

        $storage = Storage::disk($disk);

        return $storage->exists($path) && $storage->delete($path);
    }

    /**
     * Delete a temporary upload *and* the .json sidecar Livewire leaves next
     * to it.
     */
    protected function purgeTemporaryFile(TemporaryUploadedFile $file): void
    {
        $storage = FileUploadConfiguration::storage();
        $metadata = FileUploadConfiguration::path($file->getFilename(), false).'.json';

        $file->delete();

        if ($storage->exists($metadata)) {
            $storage->delete($metadata);
        }
    }

    /**
     * Reduce caller-supplied input to a safe disk-relative path, or null when
     * it cannot be trusted.
     *
     * The previous implementation passed Str::after($filename, config('app.url'))
     * straight into File::delete(public_path(...)) with no traversal check, and
     * $filename came from the browser (AUDIT.md, section 7).
     */
    private function normalizeStoredPath(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        // Accept a full URL by keeping only its path component.
        if (str_contains($path, '://')) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        $path = str_replace('\\', '/', rawurldecode($path));
        $path = ltrim($path, '/');
        $path = preg_replace('#^storage/#', '', $path) ?? $path;

        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        return $path;
    }
}
