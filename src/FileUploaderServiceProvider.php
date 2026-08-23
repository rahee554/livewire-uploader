<?php

namespace ArtflowStudio\FileUploader;

use ArtflowStudio\FileUploader\Console\InstallCommand;
use ArtflowStudio\FileUploader\Console\PublishAssetsCommand;
use ArtflowStudio\FileUploader\Livewire\UploaderTestbed;
use ArtflowStudio\FileUploader\Support\InstanceIdHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FileUploaderServiceProvider extends ServiceProvider
{
    public const VIEW_NAMESPACE = 'af-uploader';

    /** Where published assets land under public/. */
    public const ASSET_PATH = 'vendor/af-uploader';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/uploader.php', 'af-uploader');

        // Restarts the per-component id counters on every render so instance
        // ids reproduce exactly. See Support/InstanceIdHook.
        Livewire::componentHook(InstanceIdHook::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', self::VIEW_NAMESPACE);

        Blade::component(self::VIEW_NAMESPACE.'::components.uploader', 'af-uploader');

        Blade::directive(
            'afUploaderAssets',
            fn () => '<?php echo \\'.self::class.'::assetTags(); ?>'
        );

        $this->registerPublishing();
        $this->registerTestbed();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                PublishAssetsCommand::class,
            ]);
        }
    }

    /**
     * Markup emitted by the @afUploaderAssets directive.
     *
     * Both files carry a cache-busting query derived from their published
     * mtime, so re-publishing invalidates the browser cache without anyone
     * having to remember to hard-refresh.
     */
    public static function assetTags(): string
    {
        return sprintf(
            '<link rel="stylesheet" href="%s">'."\n".'<script type="module" src="%s"></script>',
            e(self::assetUrl('css/uploader.css')),
            e(self::assetUrl('js/uploader.js')),
        );
    }

    public static function assetUrl(string $file): string
    {
        $relative = self::ASSET_PATH.'/'.ltrim($file, '/');
        $absolute = public_path($relative);

        $version = is_file($absolute) ? (string) filemtime($absolute) : null;

        return asset($relative).($version ? '?id='.$version : '');
    }

    /** Absolute path to the packaged (unpublished) asset source. */
    public static function packagedAssetPath(string $file = ''): string
    {
        return rtrim(__DIR__.'/../resources/'.ltrim($file, '/'), '/');
    }

    /**
     * The exercise page at /af-uploader/testbed.
     *
     * Off unless af-uploader.testbed.enabled says otherwise, and that defaults
     * to local and testing only — a demo route reachable in production is how
     * the previous build shipped its test components to consumers.
     */
    private function registerTestbed(): void
    {
        $enabled = config('af-uploader.testbed.enabled');

        if ($enabled === null) {
            $enabled = $this->app->environment('local', 'testing');
        }

        if (! $enabled) {
            return;
        }

        Livewire::component('af-uploader-testbed', UploaderTestbed::class);

        $path = (string) config('af-uploader.testbed.path', 'af-uploader/testbed');

        Route::middleware('web')
            ->get($path, UploaderTestbed::class)
            ->name('af-uploader.testbed');
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/uploader.php' => config_path('af-uploader.php'),
        ], 'af-uploader-config');

        $this->publishes([
            __DIR__.'/../resources/css' => public_path(self::ASSET_PATH.'/css'),
            __DIR__.'/../resources/js' => public_path(self::ASSET_PATH.'/js'),
        ], 'af-uploader-assets');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/af-uploader'),
        ], 'af-uploader-views');
    }
}
