<?php

namespace ArtflowStudio\FileUploader\Tests;

use ArtflowStudio\FileUploader\FileUploaderServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            FileUploaderServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('app.key', 'base64:Hupx3yAySly9S96f1q571gPBA92pY5tV74w6+4n0y6Y=');
    }
}
