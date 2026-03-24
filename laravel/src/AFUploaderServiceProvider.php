<?php

namespace AF\Uploader;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AFUploaderServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'af');

        // Register the <x-af-uploader /> component
        Blade::component('af::components.uploader', 'af-uploader');

        // Register the @afUploaderAssets directive
        Blade::directive('afUploaderAssets', function () {
            return "
                <link rel='stylesheet' href='".asset('vendor/af-uploader/main.css')."'>
                <script type='module' src='".asset('vendor/af-uploader/index.js')."'></script>
            ";
        });
    }

    public function register()
    {
        //
    }
}
