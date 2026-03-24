<?php

namespace ArtflowStudio\FileUploader\Tests;

use ArtflowStudio\FileUploader\Livewire\TestUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

require_once __DIR__.'/TestCase.php';

class UploaderTest extends TestCase
{
    /** @test */
    public function it_can_render_the_test_uploader()
    {
        Livewire::test(TestUploader::class)
            ->assertStatus(200)
            ->assertSee('Uploader Test Suite');
    }

    /** @test */
    public function it_can_upload_a_file()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg');

        Livewire::test(TestUploader::class)
            ->set('file', $file)
            ->assertSet('file', function ($value) {
                return $value instanceof TemporaryUploadedFile;
            });
    }

    /** @test */
    public function it_can_upload_multiple_files()
    {
        Storage::fake('public');

        $files = [
            UploadedFile::fake()->image('photo1.jpg'),
            UploadedFile::fake()->image('photo2.jpg'),
        ];

        Livewire::test(TestUploader::class)
            ->set('photo_a', $files[0])
            ->set('photo_b', $files[1])
            ->assertSet('photo_a', function ($value) {
                return $value instanceof TemporaryUploadedFile;
            })
            ->assertSet('photo_b', function ($value) {
                return $value instanceof TemporaryUploadedFile;
            });
    }
}
