<?php

namespace ArtflowStudio\FileUploader\Livewire;

use ArtflowStudio\FileUploader\Traits\WithAFUploader;
use Livewire\Component;

class UploaderTest4 extends Component
{
    use WithAFUploader;

    public $video;

    public function render()
    {
        return <<<'HTML'
        <div>
            <x-af-uploader 
                wire:model="video" 
                label="Drop video here"
                height="180px"
                accept="video/*"
            />
            
            @if ($video)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Video: {{ $video->getClientOriginalName() }}
                </p>
            @endif
        </div>
        HTML;
    }
}
