<?php

namespace ArtflowStudio\FileUploader\Livewire;

use ArtflowStudio\FileUploader\Traits\WithAFUploader;
use Livewire\Component;

class UploaderTest2 extends Component
{
    use WithAFUploader;

    public $photo;

    public function render()
    {
        return <<<'HTML'
        <div>
            <x-af-uploader 
                wire:model="photo" 
                cropper="true"
                ratio="16/9"
                label="Upload & Crop"
                height="160px"
            />
            
            @if ($photo)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Cropped: {{ $photo->getClientOriginalName() }}
                </p>
            @endif
        </div>
        HTML;
    }
}
