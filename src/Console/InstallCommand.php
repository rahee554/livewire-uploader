<?php

namespace ArtflowStudio\FileUploader\Console;

use Illuminate\Console\Command;

/**
 * One command to get from `composer require` to a working uploader.
 */
class InstallCommand extends Command
{
    protected $signature = 'af-uploader:install {--config : Also publish the config file}';

    protected $description = 'Publish AF Uploader assets and print the remaining setup steps';

    public function handle(): int
    {
        $this->call('af-uploader:publish', ['--force' => true]);

        if ($this->option('config')) {
            $this->callSilently('vendor:publish', ['--tag' => 'af-uploader-config']);
            $this->components->info('Published config/af-uploader.php.');
        }

        $this->newLine();
        $this->components->info('Add the assets to your layout <head>:');
        $this->line('    @afUploaderAssets');
        $this->newLine();
        $this->components->info('Then use the component in any Livewire view:');
        $this->line('    <x-af-uploader wire:model="photo" editor ratio="1" />');
        $this->newLine();
        $this->components->info('And add the trait to the component class:');
        $this->line('    use ArtflowStudio\FileUploader\Concerns\WithUploader;');

        return self::SUCCESS;
    }
}
