<?php

namespace ArtflowStudio\FileUploader\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AssetUpdateCommand extends Command
{
    protected $signature = 'af-uploader:update-assets {--force : Force update even if files are identical}';

    protected $description = 'Check and update AF Uploader assets to the latest version';

    public function handle(): int
    {
        $this->info('🔄 AF Uploader Asset Update');
        $this->newLine();

        $packagePath = $this->getPackagePath();
        $publicPath = public_path('vendor/af-uploader');

        if (! File::exists($packagePath)) {
            $this->error('Package assets not found at: '.$packagePath);

            return 1;
        }

        $updated = 0;
        $skipped = 0;
        $missing = 0;

        // Check JS files
        $jsFiles = [
            'js/index.js',
            'js/CanvasEngine.js',
            'js/ExportEngine.js',
            'js/ImageLoader.js',
            'js/LivewireAdapter.js',
            'js/TransformEngine.js',
            'js/Uploader.js',
        ];

        $cssFiles = [
            'css/main.css',
        ];

        $allFiles = array_merge($jsFiles, $cssFiles);

        $this->info('Checking assets...');
        $this->newLine();

        foreach ($allFiles as $file) {
            $sourcePath = $packagePath.'/'.$file;
            $destPath = $publicPath.'/'.$file;

            if (! File::exists($sourcePath)) {
                $this->warn("  ⚠ Source not found: {$file}");

                continue;
            }

            if (! File::exists($destPath)) {
                $missing++;
                $this->line("  📦 Missing: {$file}");

                continue;
            }

            $sourceHash = md5_file($sourcePath);
            $destHash = md5_file($destPath);

            if ($sourceHash !== $destHash || $this->option('force')) {
                $updated++;
                $this->line("  🔄 Outdated: {$file}");
            } else {
                $skipped++;
                $this->line("  ✓ Up-to-date: {$file}");
            }
        }

        $this->newLine();

        if ($missing > 0 || $updated > 0) {
            $this->warn("Found {$updated} outdated and {$missing} missing files.");
            $this->newLine();

            if ($this->confirm('Do you want to update/publish the assets now?', true)) {
                $this->call('vendor:publish', [
                    '--tag' => 'af-uploader-assets',
                    '--force' => true,
                ]);

                $this->newLine();
                $this->info('✅ Assets updated successfully!');
            }
        } else {
            $this->info('✅ All assets are up-to-date!');
        }

        return 0;
    }

    protected function getPackagePath(): string
    {
        // Try vendor path first
        $vendorPath = base_path('vendor/artflow-studio/file-uploader/public');
        if (File::exists($vendorPath)) {
            return $vendorPath;
        }

        // Try packages path (for local development)
        $packagesPath = base_path('packages/artflow-studio/file-uploader/public');
        if (File::exists($packagesPath)) {
            return $packagesPath;
        }

        return $vendorPath;
    }
}
