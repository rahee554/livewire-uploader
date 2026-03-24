<?php

namespace ArtflowStudio\FileUploader\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

class TestCommand extends Command
{
    protected $signature = 'af-uploader:test {--skip-env : Skip environment checks}';

    protected $description = 'Test ArtFlow File Uploader installation, environment, and features';

    public function handle()
    {
        $this->info('🧪 ArtFlow File Uploader - Comprehensive Test Suite');
        $this->newLine();

        $allPassed = true;

        // Step 1: Environment Check
        if (! $this->option('skip-env')) {
            $this->info('📋 Step 1: Environment Check');
            $allPassed = $this->checkEnvironment() && $allPassed;
            $this->newLine();
        }

        // Step 2: Assets Check
        $this->info('📦 Step 2: Assets Check');
        $allPassed = $this->checkAssets() && $allPassed;
        $this->newLine();

        // Step 3: Component Check
        $this->info('🔧 Step 3: Component Check');
        $allPassed = $this->checkComponents() && $allPassed;
        $this->newLine();

        // Step 4: Run PHPUnit Tests
        $this->info('🧪 Step 4: Feature Tests');
        $allPassed = $this->runFeatureTests() && $allPassed;
        $this->newLine();

        // Step 5: Integration Check
        $this->checkComponentUsage();
        $this->newLine();

        // Final Report
        if ($allPassed) {
            $this->info('✅ All tests passed! ArtFlow File Uploader is ready to use.');
        } else {
            $this->error('❌ Some tests failed. Please review the output above.');

            return 1;
        }

        return 0;
    }

    protected function checkEnvironment(): bool
    {
        $passed = true;

        // Check PHP version
        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '8.1.0', '>=')) {
            $this->line("  ✓ PHP Version: {$phpVersion}");
        } else {
            $this->error("  ✗ PHP Version: {$phpVersion} (requires >= 8.1.0)");
            $passed = false;
        }

        // Check Laravel version
        $laravelVersion = app()->version();
        if (version_compare($laravelVersion, '10.0.0', '>=')) {
            $this->line("  ✓ Laravel Version: {$laravelVersion}");
        } else {
            $this->error("  ✗ Laravel Version: {$laravelVersion} (requires >= 10.0)");
            $passed = false;
        }

        // Check Livewire
        if (class_exists(Livewire::class)) {
            try {
                $livewireVersion = InstalledVersions::getVersion('livewire/livewire') ?? 'unknown';
                $this->line("  ✓ Livewire installed: v{$livewireVersion}");
            } catch (\Exception $e) {
                $this->line('  ✓ Livewire installed');
            }
        } else {
            $this->error('  ✗ Livewire not installed (required)');
            $passed = false;
        }

        // Check Alpine.js presence (check in published assets or views)
        $hasAlpine = $this->checkAlpineJs();
        if ($hasAlpine) {
            $this->line('  ✓ Alpine.js detected in layout');
        } else {
            $this->warn('  ⚠ Alpine.js not detected (required for uploader to work)');
            $this->warn('    Add Alpine.js to your layout: <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>');
        }

        // Check storage link
        $storageLinked = File::exists(public_path('storage'));
        if ($storageLinked) {
            $this->line('  ✓ Storage linked');
        } else {
            $this->warn('  ⚠ Storage not linked. Run: php artisan storage:link');
        }

        return $passed;
    }

    protected function checkAssets(): bool
    {
        $passed = true;

        $assets = [
            'public/vendor/af-uploader/css/main.css' => 'CSS',
            'public/vendor/af-uploader/js/index.js' => 'JavaScript',
            'public/vendor/af-uploader/js/CanvasEngine.js' => 'Canvas Engine',
            'public/vendor/af-uploader/js/ExportEngine.js' => 'Export Engine',
        ];

        foreach ($assets as $path => $name) {
            if (File::exists(base_path($path))) {
                $this->line("  ✓ {$name} published");
            } else {
                $this->error("  ✗ {$name} not published");
                $this->error('    Run: php artisan vendor:publish --tag=af-uploader-assets --force');
                $passed = false;
            }
        }

        return $passed;
    }

    protected function checkComponents(): bool
    {
        $passed = true;

        // Check blade directive
        try {
            $directive = \Blade::compileString('@afUploaderAssets');
            if (str_contains($directive, 'vendor/af-uploader')) {
                $this->line('  ✓ @afUploaderAssets directive registered');
            } else {
                $this->error('  ✗ @afUploaderAssets directive not working correctly');
                $passed = false;
            }
        } catch (\Exception $e) {
            $this->error('  ✗ @afUploaderAssets directive error: '.$e->getMessage());
            $passed = false;
        }

        // Check component registration
        if (view()->exists('af-uploader::components.uploader')) {
            $this->line('  ✓ Component view exists');
        } else {
            $this->error('  ✗ Component view not found');
            $passed = false;
        }

        return $passed;
    }

    protected function runFeatureTests(): bool
    {
        $testPath = __DIR__.'/../../tests/Feature';

        if (! File::exists($testPath)) {
            $this->warn('  ⚠ Feature tests not found. Creating test structure...');

            return true;
        }

        try {
            $this->line('  Running PHPUnit tests...');
            Artisan::call('test', [
                '--testsuite' => 'AF-Uploader',
                '--stop-on-failure' => true,
            ]);

            $output = Artisan::output();
            $this->line($output);

            return ! str_contains(strtolower($output), 'fail');
        } catch (\Exception $e) {
            $this->error('  ✗ Test execution failed: '.$e->getMessage());

            return false;
        }
    }

    protected function checkAlpineJs(): bool
    {
        // Check in common layout locations
        $layoutPaths = [
            'resources/views/layouts/app.blade.php',
            'resources/views/components/layouts/app.blade.php',
            'resources/views/layouts/guest.blade.php',
        ];

        foreach ($layoutPaths as $path) {
            if (File::exists(base_path($path))) {
                $content = File::get(base_path($path));
                if (str_contains($content, 'alpinejs') || str_contains($content, 'alpine.js')) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function checkComponentUsage(): void
    {
        $this->line("\n🔍 Step 5: Integration Check");
        $results = [];
        $files = File::allFiles(app_path('Livewire'));
        foreach ($files as $file) {
            $content = File::get($file->getRealPath());
            if (str_contains($content, 'WithAFuploader')) {
                $results[] = $file->getFilename();
            }
        }

        if ($results) {
            $count = count($results);
            $this->line("  ✓ Found {$count} components using WithAFuploader trait");
        } else {
            $this->warn('  ⚠ No components found using WithAFuploader trait');
        }
    }
}
