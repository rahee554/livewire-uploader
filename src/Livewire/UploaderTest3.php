<?php

namespace ArtflowStudio\FileUploader\Livewire;

use ArtflowStudio\FileUploader\Traits\WithAFUploader;
use Livewire\Component;

class UploaderTest3 extends Component
{
    use WithAFUploader;

    public $avatar;

    public function render()
    {
        return <<<'HTML'
        <div class="flex flex-col items-center">
            <x-af-uploader 
                wire:model="avatar" 
                variant="circled"
                cropper="true"
                is-circle="true"
                width="180px"
                height="180px"
                label="Avatar"
            />
            
            @if ($avatar)
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    Avatar uploaded!
                </p>
            @endif
        </div>
        HTML;
    }
}
