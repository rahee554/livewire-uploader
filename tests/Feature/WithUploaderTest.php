<?php

namespace ArtflowStudio\FileUploader\Tests\Feature;

use ArtflowStudio\FileUploader\Concerns\WithUploader;
use ArtflowStudio\FileUploader\Exceptions\UploadRejected;
use ArtflowStudio\FileUploader\Tests\TestCase;
use ArtflowStudio\FileUploader\Traits\WithAFuploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;

class WithUploaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('livewire-tmp');
    }

    #[Test]
    public function it_stores_an_upload_and_returns_a_relative_path(): void
    {
        Livewire::test(UploaderHarness::class)
            ->set('photo', UploadedFile::fake()->image('avatar.jpg'))
            ->call('save')
            ->assertSet('stored', fn ($path) => str_starts_with($path, 'uploads/'));

        $this->assertCount(1, Storage::disk('public')->files('uploads'));
    }

    #[Test]
    public function storing_removes_the_temporary_file_and_its_metadata(): void
    {
        // Livewire writes a .json sidecar next to every temp upload and only
        // ever deletes the file, so livewire-tmp grows forever (AUDIT.md).
        $component = Livewire::test(UploaderHarness::class)
            ->set('photo', UploadedFile::fake()->image('avatar.jpg'));

        /** @var TemporaryUploadedFile $temporary */
        $temporary = $component->get('photo');
        $filename = $temporary->getFilename();

        $component->call('save');

        $remaining = Storage::disk('livewire-tmp')->allFiles();

        $this->assertNotContains($filename, $remaining);
        $this->assertNotContains($filename.'.json', $remaining);
    }

    #[Test]
    public function a_rejected_file_is_not_written(): void
    {
        $this->expectException(UploadRejected::class);

        Livewire::test(UploaderHarness::class)
            ->set('photo', UploadedFile::fake()->create('shell.php', 1))
            ->call('save');

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    #[Test]
    public function an_unknown_disk_is_reported_clearly(): void
    {
        $this->expectException(UploadRejected::class);
        $this->expectExceptionMessage('is not configured');

        Livewire::test(UploaderHarness::class)
            ->set('photo', UploadedFile::fake()->image('a.jpg'))
            ->call('saveTo', 'nope');
    }

    #[Test]
    public function discard_clears_the_property(): void
    {
        Livewire::test(UploaderHarness::class)
            ->set('photo', UploadedFile::fake()->image('avatar.jpg'))
            ->call('discardUpload', 'photo')
            ->assertSet('photo', null);
    }

    #[Test]
    public function discard_removes_only_the_named_file_from_an_array(): void
    {
        Livewire::test(UploaderHarness::class)
            ->set('gallery', [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ])
            ->call('discardFirst')
            ->assertCount('gallery', 1);
    }

    #[Test]
    public function store_uploads_reports_rejections_without_aborting_the_batch(): void
    {
        Livewire::test(UploaderHarness::class)
            ->set('gallery', [
                UploadedFile::fake()->image('good.jpg'),
                UploadedFile::fake()->create('bad.php', 1),
            ])
            ->call('saveGallery')
            ->assertCount('storedMany', 1)
            ->assertCount('rejections', 1);
    }

    #[Test]
    public function deleting_a_stored_file_removes_it_from_disk(): void
    {
        Storage::disk('public')->put('uploads/keep.jpg', 'x');

        Livewire::test(UploaderHarness::class)
            ->call('deleteStoredUpload', 'stored', 'uploads/keep.jpg');

        Storage::disk('public')->assertMissing('uploads/keep.jpg');
    }

    #[Test]
    public function a_traversal_path_is_refused(): void
    {
        Storage::disk('public')->put('secret.txt', 'x');

        Livewire::test(UploaderHarness::class)
            ->call('deleteStoredUpload', 'stored', '../../secret.txt')
            ->assertReturned(false);

        Storage::disk('public')->assertExists('secret.txt');
    }

    #[Test]
    public function a_full_url_is_reduced_to_a_disk_relative_path(): void
    {
        Storage::disk('public')->put('uploads/pic.jpg', 'x');

        Livewire::test(UploaderHarness::class)
            ->call('deleteStoredUpload', 'stored', 'http://localhost/storage/uploads/pic.jpg')
            ->assertReturned(true);

        Storage::disk('public')->assertMissing('uploads/pic.jpg');
    }

    #[Test]
    public function the_deprecated_trait_still_works(): void
    {
        $component = new LegacyHarness;

        $this->assertContains(
            WithAFuploader::class,
            class_uses_recursive($component)
        );
        $this->assertTrue(method_exists($component, 'storeAFUpload'));
        $this->assertTrue(method_exists($component, 'storeUpload'));
    }
}

class UploaderHarness extends Component
{
    use WithUploader;

    public $photo;

    public $gallery = [];

    public ?string $stored = null;

    public array $storedMany = [];

    public array $rejections = [];

    public function save(): void
    {
        $this->stored = $this->storeUpload($this->photo, accept: 'image/*');
    }

    public function saveTo(string $disk): void
    {
        $this->stored = $this->storeUpload($this->photo, disk: $disk);
    }

    public function saveGallery(): void
    {
        [$this->storedMany, $this->rejections] = $this->storeUploads($this->gallery);
    }

    public function discardFirst(): void
    {
        $this->discardUpload('gallery', $this->gallery[0]->getFilename());
    }

    public function render(): string
    {
        return '<div>harness</div>';
    }
}

class LegacyHarness extends Component
{
    use WithAFuploader;

    public function render(): string
    {
        return '<div>legacy</div>';
    }
}
