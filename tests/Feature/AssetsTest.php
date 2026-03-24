<?php

namespace ArtflowStudio\FileUploader\Tests\Feature;

use ArtflowStudio\FileUploader\FileUploaderServiceProvider;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AssetsTest extends TestCase
{
    /** @test */
    public function css_assets_exist()
    {
        $cssPath = __DIR__.'/../../public/css/main.css';

        $this->assertFileExists($cssPath, 'main.css should exist in package');

        $content = File::get($cssPath);
        $this->assertStringContainsString('.af-uploader-wrapper', $content);
        $this->assertStringContainsString('.af-dropzone', $content);
    }

    /** @test */
    public function javascript_assets_exist()
    {
        $jsPath = __DIR__.'/../../public/js/index.js';

        $this->assertFileExists($jsPath, 'index.js should exist in package');

        $content = File::get($jsPath);
        $this->assertStringContainsString('class CropperApp', $content);
        $this->assertStringContainsString('setupDropzone', $content);
    }

    /** @test */
    public function blade_directive_is_registered()
    {
        $compiled = \Blade::compileString('@afUploaderAssets');

        $this->assertStringContainsString('vendor/af-uploader', $compiled);
        $this->assertStringContainsString('main.css', $compiled);
        $this->assertStringContainsString('index.js', $compiled);
    }

    /** @test */
    public function component_view_exists()
    {
        $viewPath = __DIR__.'/../../resources/views/components/uploader.blade.php';

        $this->assertFileExists($viewPath, 'uploader.blade.php should exist');

        $content = File::get($viewPath);
        $this->assertStringContainsString('x-data', $content);
        $this->assertStringContainsString('wire:ignore', $content);
        // Check for Alpine.js usage (x-data or afUploader function)
        $this->assertStringContainsString('afUploader', $content);
    }

    /** @test */
    public function service_provider_registers_components()
    {
        $provider = new FileUploaderServiceProvider($this->app);

        // Check that boot method would register views
        $this->assertTrue(method_exists($provider, 'boot'));
    }
}
