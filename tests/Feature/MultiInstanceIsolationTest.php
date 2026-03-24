<?php

namespace Tests\Feature;

use ArtflowStudio\FileUploader\Traits\WithAFuploader;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class MultiInstanceIsolationTest extends TestCase
{
    /** @test */
    public function it_can_render_multiple_instances_on_the_same_page()
    {
        $component = new class extends Component
        {
            use WithAFuploader;

            public $photo1;

            public $photo2;

            public function render()
            {
                return <<<'BLADE'
                    <div>
                        <x-af-uploader wire:model="photo1" label="Uploader 1" id="up1" />
                        <x-af-uploader wire:model="photo2" label="Uploader 2" id="up2" />
                    </div>
                BLADE;
            }
        };

        Livewire::test(get_class($component))
            ->assertSee('Uploader 1')
            ->assertSee('Uploader 2')
            ->assertSee('af-upl-up1')
            ->assertSee('af-upl-up2');
    }
}
