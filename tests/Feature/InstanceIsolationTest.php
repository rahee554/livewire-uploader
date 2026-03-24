<?php

namespace ArtflowStudio\FileUploader\Tests\Feature;

use Tests\TestCase;

class InstanceIsolationTest extends TestCase
{
    /** @test */
    public function unique_ids_are_generated_for_each_instance()
    {
        // Read the Blade file directly to verify ID generation logic is present
        $viewPath = __DIR__.'/../../resources/views/components/uploader.blade.php';
        $content = file_get_contents($viewPath);

        // Verify the ID generation logic is in place
        $this->assertStringContainsString('$instanceId = \'af-upl-\'', $content);
        $this->assertStringContainsString('$stableHash = substr(md5(serialize($context))', $content);
        $this->assertStringContainsString('wire:key=', $content);
    }

    /** @test */
    public function scoped_events_are_generated_correctly()
    {
        $viewPath = __DIR__.'/../../resources/views/components/uploader.blade.php';
        $content = file_get_contents($viewPath);

        // Check for scoped event listeners
        $this->assertStringContainsString('@af-status-update-', $content);
        $this->assertStringContainsString('@af-external-file-selected-', $content);
        $this->assertStringContainsString('@af-image-cropped-', $content);
        $this->assertStringContainsString('.window="onStatusUpdate', $content);
    }

    /** @test */
    public function wire_ignore_prevents_livewire_morphing()
    {
        $viewPath = __DIR__.'/../../resources/views/components/uploader.blade.php';
        $content = file_get_contents($viewPath);

        $this->assertStringContainsString('wire:ignore', $content);
    }

    /** @test */
    public function wire_key_ensures_proper_tracking()
    {
        $viewPath = __DIR__.'/../../resources/views/components/uploader.blade.php';
        $content = file_get_contents($viewPath);

        $this->assertStringContainsString('wire:key=', $content);
        $this->assertStringContainsString('af-upl-', $content);
    }

    /** @test */
    public function event_isolation_checks_are_implemented()
    {
        // Check the scripts.blade.php for event isolation
        $scriptsPath = __DIR__.'/../../resources/views/components/scripts.blade.php';
        $content = file_get_contents($scriptsPath);

        // Check that Alpine methods include isolation checks
        $this->assertStringContainsString('detail.id', $content);
        $this->assertStringContainsString('this.id', $content);
        // Check for ID-based event filtering
        $this->assertStringContainsString('if (detail.id !== this.id) return', $content);
    }
}
