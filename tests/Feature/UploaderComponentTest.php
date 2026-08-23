<?php

namespace ArtflowStudio\FileUploader\Tests\Feature;

use ArtflowStudio\FileUploader\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;

class UploaderComponentTest extends TestCase
{
    private function render(string $template, array $data = []): string
    {
        return Blade::render($template, $data);
    }

    /**
     * Js::from() serialises to JSON.parse('...') and escapes every double
     * quote as a unicode escape so the payload is safe inside an HTML
     * attribute. Undo that one substitution so assertions below can be written
     * against readable JSON.
     */
    private function config(string $html): string
    {
        $escapedQuote = '\\'.'u0022';

        return str_replace($escapedQuote, '"', $html);
    }

    #[Test]
    public function it_renders(): void
    {
        $html = $this->render('<x-af-uploader wire:model="photo" label="Pick a photo" />');

        $this->assertStringContainsString('af-uploader-wrapper', $html);
        $this->assertStringContainsString('af-dropzone', $html);
        $this->assertStringContainsString('Pick a photo', $html);
        $this->assertStringContainsString('type="file"', $html);
    }

    #[Test]
    public function two_instances_get_different_ids(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-af-uploader wire:model="photo" />
            <x-af-uploader wire:model="photo" />
        BLADE);

        preg_match_all('/wire:key="(af-[a-z0-9]+)"/', $html, $matches);

        $this->assertCount(2, $matches[1]);
        $this->assertNotSame($matches[1][0], $matches[1][1]);
    }

    #[Test]
    public function an_explicit_id_is_used_verbatim(): void
    {
        $html = $this->render('<x-af-uploader wire:model="photo" id="avatar" />');

        $this->assertStringContainsString('wire:key="af-avatar"', $html);
        $this->assertStringContainsString('id="af-avatar"', $html);
    }

    #[Test]
    public function options_are_serialised_into_x_data(): void
    {
        $html = $this->render('<x-af-uploader wire:model="photo" max-size="4" accept="image/png" />');

        $this->assertStringContainsString('afUploader(', $html);
        $this->assertStringContainsString('"maxSize":4', $this->config($html));
        $this->assertStringContainsString('"model":"photo"', $this->config($html));
        $this->assertStringContainsString('accept="image/png"', $html);
    }

    #[Test]
    public function auto_upload_false_is_serialised_as_false(): void
    {
        $html = $this->render('<x-af-uploader wire:model="photo" auto-upload="false" />');

        $this->assertStringContainsString('"autoUpload":false', $this->config($html));
    }

    #[Test]
    public function multiple_puts_the_attribute_on_the_input(): void
    {
        $html = $this->render('<x-af-uploader wire:model="files" multiple />');

        $this->assertMatchesRegularExpression('/<input[^>]*\smultiple[\s>]/s', $html);
        $this->assertStringContainsString('"multiple":true', $this->config($html));
    }

    #[Test]
    public function the_input_never_carries_a_wire_model(): void
    {
        // Livewire would bind the input itself and race the Alpine uploader.
        $html = $this->render('<x-af-uploader wire:model="photo" />');

        preg_match('/<input[^>]*type="file".*?>/s', $html, $input);

        $this->assertNotEmpty($input);
        $this->assertStringNotContainsString('wire:model', $input[0]);
    }

    #[Test]
    public function no_stale_data_attribute_channel_remains(): void
    {
        // The old build carried a parallel data-af-* config that drifted out
        // of sync with the Alpine options (AUDIT.md, HIGH-05).
        $html = $this->render('<x-af-uploader wire:model="photo" cropper ratio="16/9" target-size="500KB" />');

        $this->assertStringNotContainsString('data-af-', $html);
    }

    #[Test]
    public function the_variant_prop_selects_a_class(): void
    {
        $this->assertStringContainsString(
            'af-dz-circled',
            $this->render('<x-af-uploader wire:model="photo" variant="circled" />')
        );

        $this->assertStringContainsString(
            'af-dz-plain',
            $this->render('<x-af-uploader wire:model="photo" />')
        );
    }

    #[Test]
    public function dimensions_gain_a_pixel_unit_when_bare(): void
    {
        $html = $this->render('<x-af-uploader wire:model="photo" height="180" width="50%" />');

        $this->assertStringContainsString('height: 180px;', $html);
        $this->assertStringContainsString('width: 50%;', $html);
    }

    #[Test]
    public function it_renders_without_a_bound_model(): void
    {
        $html = $this->render('<x-af-uploader />');

        $this->assertStringContainsString('af-dropzone', $html);
        $this->assertStringNotContainsString('entangle', $html);
    }

    #[Test]
    public function the_assets_directive_emits_both_tags(): void
    {
        $html = Blade::render('@afUploaderAssets');

        $this->assertStringContainsString('vendor/af-uploader/css/uploader.css', $html);
        $this->assertStringContainsString('vendor/af-uploader/js/uploader.js', $html);
        $this->assertStringContainsString('type="module"', $html);
    }
}
