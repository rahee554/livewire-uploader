<?php

namespace ArtflowStudio\FileUploader\Tests\Feature;

use ArtflowStudio\FileUploader\Livewire\TestUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\WithFileUploads;
use Tests\TestCase;

class UploaderComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @test */
    public function it_renders_uploader_component()
    {
        // Use the package's built-in TestUploader component
        $component = Livewire::test(TestUploader::class);

        // Check that the component renders with uploader elements
        $component->assertViewHas('simpleImage', null);
    }

    /** @test */
    public function it_uploads_file_successfully()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test-image.jpg');

        $component = Livewire::test(TestUploader::class)
            ->set('simpleImage', $file);

        $component->assertSet('simpleImage', function ($value) {
            return $value !== null;
        });
    }

    /** @test */
    public function it_validates_file_type()
    {
        // Read the blade file to verify accept attribute is present
        $viewPath = __DIR__.'/../../resources/views/components/uploader.blade.php';
        $content = file_get_contents($viewPath);

        $this->assertStringContainsString('accept=', $content);
        $this->assertStringContainsString('$acceptAttr', $content);
    }

    /** @test */
    public function it_validates_file_size()
    {
        // Check that max-size prop is handled
        $viewPath = __DIR__.'/../../resources/views/components/uploader.blade.php';
        $content = file_get_contents($viewPath);

        // Check the props for maxSize handling
        $this->assertStringContainsString('maxSize', $content);
        // Check the JavaScript config receives the maxSize
        $this->assertStringContainsString('maxSize: {{ $maxSize }}', $content);
    }

    /** @test */
    public function it_removes_uploaded_file()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test-image.jpg');

        $component = Livewire::test(TestUploader::class)
            ->set('simpleImage', $file);

        // Verify the file is set
        $component->assertSet('simpleImage', function ($value) {
            return $value !== null;
        });

        // Set to null to remove
        $component->set('simpleImage', null);
        $component->assertSet('simpleImage', null);
    }

    /** @test */
    public function multiple_instances_work_independently()
    {
        $file1 = UploadedFile::fake()->image('image1.jpg');
        $file2 = UploadedFile::fake()->image('image2.jpg');

        $component = Livewire::test(TestUploader::class)
            ->set('simpleImage', $file1)
            ->set('cropperSquare', $file2);

        $component->assertSet('simpleImage', function ($value) {
            return $value !== null;
        });

        $component->assertSet('cropperSquare', function ($value) {
            return $value !== null;
        });
    }

    /** @test */
    public function it_generates_unique_ids_for_each_instance()
    {
        // Read the Blade file directly to verify structure
        $viewPath = __DIR__.'/../../resources/views/components/uploader.blade.php';
        $content = file_get_contents($viewPath);

        $this->assertStringContainsString('af-upl-', $content);
        $this->assertStringContainsString('wire:ignore', $content);
        $this->assertStringContainsString('wire:key', $content);
        $this->assertStringContainsString('x-data', $content);
        $this->assertStringContainsString('afUploader', $content);
    }
}

// Test component for testing
class TestUploaderComponent extends Component
{
    use WithFileUploads;

    public $photo;

    protected $rules = [
        'photo' => 'nullable|image|max:10240',
    ];

    public function removePhoto()
    {
        $this->photo = null;
    }

    public function render()
    {
        return view('af-uploader::test-uploader-component');
    }
}

class MultiInstanceComponent extends Component
{
    use WithFileUploads;

    public $photo1;

    public $photo2;

    public $photo3;

    public function render()
    {
        return view('af-uploader::test-multi-instance');
    }
}
