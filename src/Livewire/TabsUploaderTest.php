<?php

namespace ArtflowStudio\FileUploader\Livewire;

use ArtflowStudio\FileUploader\Traits\WithAFUploader;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Test component for verifying uploader persistence across tab switches.
 *
 * This mimics the common pattern where uploaders are shown/hidden based on user selection.
 */
class TabsUploaderTest extends Component
{
    use WithAFUploader;

    public string $activeTab = 'image';

    public $imageFile;

    public $videoFile;

    public $documentFile;

    public $cropperImageFile;

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render(): View
    {
        return view('af-uploader::livewire.tabs-uploader-test')
            ->layout('af-uploader::test-uploader');
    }
}
