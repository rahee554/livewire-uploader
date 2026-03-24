<?php

namespace ArtflowStudio\FileUploader\Livewire;

use ArtflowStudio\FileUploader\Traits\WithAFUploader;
use Livewire\Component;

class UploaderTest6 extends Component
{
    use WithAFUploader;

    public $custom;

    public function render()
    {
        return <<<'HTML'
        <div>
            <x-af-uploader 
                wire:model="custom" 
                label="Custom size"
                width="100%"
                height="220px"
            />
            
            @if ($custom)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ $custom->getClientOriginalName() }}
                </p>
            @endif
        </div>
        HTML;
    }
}
