<?php

namespace ArtflowStudio\FileUploader\Livewire;

use ArtflowStudio\FileUploader\Traits\WithAFUploader;
use Livewire\Component;

class UploaderTest5 extends Component
{
    use WithAFUploader;

    public $document;

    public function render()
    {
        return <<<'HTML'
        <div>
            <x-af-uploader 
                wire:model="document" 
                label="Drop document"
                height="180px"
                accept=".pdf,.doc,.docx"
            />
            
            @if ($document)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Doc: {{ $document->getClientOriginalName() }}
                </p>
            @endif
        </div>
        HTML;
    }
}
