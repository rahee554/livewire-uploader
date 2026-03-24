<?php

namespace ArtflowStudio\FileUploader\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait WithAFUploader
{
    use WithFileUploads;

    /**
     * Delete a temporary uploaded file AND its JSON metadata file.
     *
     * This fixes Livewire's oversight where the .json metadata file is not deleted
     * after a temporary upload is processed or discarded.
     */
    protected function deleteTemporaryFileWithMetadata(TemporaryUploadedFile $file): void
    {
        $storage = FileUploadConfiguration::storage();
        $path = FileUploadConfiguration::path($file->getFilename(), false);
        $jsonPath = $path.'.json';

        $file->delete();

        if ($storage->exists($jsonPath)) {
            $storage->delete($jsonPath);
        }
    }

    /**
     * Remove a permanently stored file from disk and clear the Livewire property.
     *
     * Use this for files that have already been saved to public storage.
     */
    public function removeUpload(string $property, string $filename): void
    {
        if (! $this->hasProperty($property)) {
            return;
        }

        $data = $this->getPropertyValue($property);
        $relativePath = Str::after($filename, config('app.url'));

        if (is_array($data)) {
            $this->{$property} = array_values(
                array_filter($data, fn (string $item) => $item !== $relativePath)
            );
        } else {
            $this->{$property} = null;
        }

        if (File::exists(public_path($relativePath))) {
            File::delete(public_path($relativePath));
        }

        $this->dispatch('af-upload-removed', property: $property);
    }

    /**
     * Revert a temporary (not yet permanently stored) upload and clear the property.
     *
     * Use this during the upload flow before the file has been moved to permanent storage.
     */
    public function revertUpload(string $property, string $filename): void
    {
        if (! $this->hasProperty($property)) {
            return;
        }

        $uploads = $this->getPropertyValue($property);

        if (! is_array($uploads)) {
            if ($uploads instanceof TemporaryUploadedFile) {
                $this->deleteTemporaryFileWithMetadata($uploads);
            }

            $this->{$property} = null;

            return;
        }

        $this->{$property} = collect($uploads)
            ->filter(function (mixed $upload) use ($filename) {
                if (! $upload instanceof TemporaryUploadedFile) {
                    return false;
                }

                if ($upload->getFilename() === $filename) {
                    $this->deleteTemporaryFileWithMetadata($upload);

                    return false;
                }

                return true;
            })->values()->toArray();

        $this->dispatch('af-upload-reverted', property: $property);
    }

    /**
     * Store a temporary upload permanently and clean up both the temp file and its
     * JSON metadata. Use this instead of calling $file->store() directly.
     */
    public function storeAFUpload(TemporaryUploadedFile $file, string $path, string $disk = 'public', ?string $name = null): string
    {
        $storage = FileUploadConfiguration::storage();
        $jsonPath = FileUploadConfiguration::path($file->getFilename(), false).'.json';

        $storedPath = $name
            ? $file->storeAs($path, $name, $disk)
            : $file->store($path, $disk);

        $file->delete();

        if ($storage->exists($jsonPath)) {
            $storage->delete($jsonPath);
        }

        return $storedPath;
    }

    /**
     * Dispatch an upload-success event to notify the JS layer of a completed store.
     */
    public function dispatchUploadSuccess(string $inputId): void
    {
        $this->dispatch('af-upload-success', inputId: $inputId);
    }
}
