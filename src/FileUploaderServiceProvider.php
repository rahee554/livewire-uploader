<?php

namespace ArtflowStudio\FileUploader;

use ArtflowStudio\FileUploader\Console\AssetUpdateCommand;
use ArtflowStudio\FileUploader\Console\TestCommand;
use ArtflowStudio\FileUploader\Livewire\TabsUploaderTest;
use ArtflowStudio\FileUploader\Livewire\TestUploader;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FileUploaderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'af-uploader');

        Blade::component('af-uploader::components.uploader', 'af-uploader');

        // Register Livewire Components
        Livewire::component('af-test-uploader', TestUploader::class);
        Livewire::component('af-tabs-uploader-test', TabsUploaderTest::class);

        // Test Routes (only in local environment)
        $this->registerTestRoutes();

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/af-uploader'),
        ], 'af-uploader-views');

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/af-uploader'),
        ], 'af-uploader-assets');

        Blade::directive('afUploaderAssets', function () {
            return "<?php echo \"<link rel='stylesheet' href='\" . asset('vendor/af-uploader/css/main.css') . \"'>\" . \"<script type='module' src='\" . asset('vendor/af-uploader/js/index.js') . \"'></script>\"; ?>";
        });

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestCommand::class,
                AssetUpdateCommand::class,
            ]);
        }
    }

    protected function registerTestRoutes(): void
    {
        if (! $this->app->environment('local', 'testing')) {
            return;
        }

        Route::middleware('web')->group(function () {
            Route::get('/af-uploader/test', TestUploader::class)->name('af-uploader.test');
            Route::get('/af-uploader/tabs-test', TabsUploaderTest::class)->name('af-uploader.tabs-test');
        });
    }
}
