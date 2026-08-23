<?php

namespace ArtflowStudio\FileUploader\Tests;

use ArtflowStudio\FileUploader\FileUploaderServiceProvider;
use ArtflowStudio\FileUploader\Support\InstanceId;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ids are handed out by a per-request counter; reset it so ordinals
        // start from zero in every test.
        InstanceId::reset();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            FileUploaderServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:Hupx3yAySly9S96f1q571gPBA92pY5tV74w6+4n0y6Y=');
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => storage_path('app/public'),
        ]);
    }
}
