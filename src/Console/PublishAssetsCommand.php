<?php

namespace ArtflowStudio\FileUploader\Console;

use ArtflowStudio\FileUploader\FileUploaderServiceProvider as Provider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SplFileInfo;

/**
 * Compares every packaged asset against what is sitting in public/ and
 * republishes when they differ.
 *
 * The command it replaces hardcoded a seven-file manifest — including three
 * modules that had already been deleted — so it silently stopped covering the
 * real asset set (AUDIT.md, section 6). This one walks the directory instead.
 */
class PublishAssetsCommand extends Command
{
    protected $signature = 'af-uploader:publish
                            {--force : Republish even when every file already matches}';

    protected $description = 'Publish the AF Uploader CSS and JS into public/vendor/af-uploader';

    public function handle(): int
    {
        $source = Provider::packagedAssetPath();
        $target = public_path(Provider::ASSET_PATH);

        $expected = $this->collect($source.'/css', 'css')
            + $this->collect($source.'/js', 'js');

        if ($expected === []) {
            $this->components->error("No packaged assets found under {$source}.");

            return self::FAILURE;
        }

        $missing = [];
        $stale = [];

        foreach ($expected as $relative => $absolute) {
            $published = $target.'/'.$relative;

            if (! File::exists($published)) {
                $missing[] = $relative;
            } elseif (md5_file($absolute) !== md5_file($published)) {
                $stale[] = $relative;
            }
        }

        $this->components->info(sprintf(
            '%d asset(s) checked — %d missing, %d outdated.',
            count($expected),
            count($missing),
            count($stale),
        ));

        foreach ([...$missing, ...$stale] as $file) {
            $this->components->twoColumnDetail($file, in_array($file, $missing, true) ? 'missing' : 'outdated');
        }

        if ($missing === [] && $stale === [] && ! $this->option('force')) {
            $this->components->success('Assets are up to date.');

            return self::SUCCESS;
        }

        $this->callSilently('vendor:publish', [
            '--tag' => 'af-uploader-assets',
            '--force' => true,
        ]);

        // Anything left over from a previous layout would still be served, so
        // report it rather than deleting files we did not put there.
        $this->reportOrphans($target, array_keys($expected));

        $this->components->success('Assets published to '.Provider::ASSET_PATH.'.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> relative path => absolute source path
     */
    private function collect(string $directory, string $prefix): array
    {
        if (! File::isDirectory($directory)) {
            return [];
        }

        $files = [];

        /** @var SplFileInfo $file */
        foreach (File::allFiles($directory) as $file) {
            $relative = $prefix.'/'.str_replace('\\', '/', $file->getRelativePathname());
            $files[$relative] = $file->getPathname();
        }

        return $files;
    }

    /**
     * @param  list<string>  $expected
     */
    private function reportOrphans(string $target, array $expected): void
    {
        if (! File::isDirectory($target)) {
            return;
        }

        $orphans = [];

        /** @var SplFileInfo $file */
        foreach (File::allFiles($target) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if (! in_array($relative, $expected, true)) {
                $orphans[] = $relative;
            }
        }

        if ($orphans === []) {
            return;
        }

        $this->newLine();
        $this->components->warn('These files are no longer part of the package and can be deleted:');

        foreach ($orphans as $orphan) {
            $this->components->bulletList([Provider::ASSET_PATH.'/'.$orphan]);
        }
    }
}
