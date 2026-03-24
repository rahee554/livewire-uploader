<?php

namespace ArtflowStudio\FileUploader\Livewire;

use ArtflowStudio\FileUploader\Traits\WithAFUploader;
use Livewire\Component;

class UploaderTest1 extends Component
{
    use WithAFUploader;

    public $photo;

    public function render()
    {
        return <<<'HTML'
        <div>
            <x-af-uploader 
                wire:model="photo" 
                label="Drop image here"
                height="180px"
            />
            
            @if ($photo)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    File: {{ $photo->getClientOriginalName() }}
                </p>
            @endif
        </div>
        HTML;
    }
}
