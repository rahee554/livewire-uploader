<?php

namespace ArtflowStudio\FileUploader\Livewire;

use ArtflowStudio\FileUploader\Concerns\WithUploader;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A live exercise page for every uploader configuration.
 *
 * Reachable at /af-uploader/testbed in local and testing environments only —
 * see FileUploaderServiceProvider::registerTestbed(). It is the page the
 * browser tests drive, and it doubles as a manual smoke check.
 *
 * Every panel exposes its state in a [data-testid] element so assertions can
 * read the server-side truth rather than scraping the UI.
 */
class UploaderTestbed extends Component
{
    use WithUploader;

    /** Every single-file property, in the order the page lays them out. */
    private const SINGLE_PROPERTIES = [
        'simple', 'manual', 'cropped', 'avatar', 'document',
        'inline', 'squared', 'rect', 'tiny', 'shrink',
        'videoOnly', 'sized', 'percent', 'both',
    ];

    /** Every multi-file property. */
    private const ARRAY_PROPERTIES = ['gallery', 'imagesOnly', 'manyFiles'];

    public $simple;

    public $manual;

    public $cropped;

    public $avatar;

    public $document;

    public $inline;

    public $squared;

    public $rect;

    public $tiny;

    public $shrink;

    public $videoOnly;

    public $sized;

    public $percent;

    public $both;

    /** @var array<int, mixed> */
    public array $imagesOnly = [];

    /** @var array<int, mixed> */
    public array $manyFiles = [];

    /** @var array<int, mixed> */
    public array $gallery = [];

    /** @var array<int, mixed> */
    public array $loopFiles = [null, null, null];

    /** Bumped to force a re-render, so ids can be checked for stability. */
    public int $renders = 0;

    /** @var list<string> */
    public array $saved = [];

    /** @var list<string> */
    public array $errors = [];

    public function rerender(): void
    {
        $this->renders++;
    }

    public function save(): void
    {
        $this->saved = [];
        $this->errors = [];

        foreach (self::SINGLE_PROPERTIES as $property) {
            if (! $this->{$property}) {
                continue;
            }

            try {
                $this->saved[] = $this->storeUpload($this->{$property}, 'testbed');
            } catch (\Throwable $e) {
                $this->errors[] = $e->getMessage();
            }
        }

        foreach (self::ARRAY_PROPERTIES as $property) {
            if ($this->{$property} === []) {
                continue;
            }

            [$stored, $rejected] = $this->storeUploads($this->{$property}, 'testbed');

            $this->saved = [...$this->saved, ...$stored];
            $this->errors = [...$this->errors, ...$rejected];
        }
    }

    public function reset_all(): void
    {
        foreach (self::SINGLE_PROPERTIES as $property) {
            $this->discardUpload($property);
        }

        foreach (self::ARRAY_PROPERTIES as $property) {
            $this->discardUpload($property);
        }

        $this->saved = [];
        $this->errors = [];
    }

    /** Reported back to the page so tests can assert on the bound state. */
    public function getStateProperty(): array
    {
        $state = [
            'gallery' => count($this->gallery),
            'imagesOnly' => count($this->imagesOnly),
            'manyFiles' => count($this->manyFiles),
        ];

        foreach (self::SINGLE_PROPERTIES as $property) {
            $state[$property] = $this->describe($this->{$property});
        }

        return $state;
    }

    private function describe(mixed $value): string
    {
        if ($value === null) {
            return 'empty';
        }

        return method_exists($value, 'getClientOriginalName')
            ? $value->getClientOriginalName()
            : 'set';
    }

    public function render(): View
    {
        return view('af-uploader::testbed.page')
            ->layout('af-uploader::testbed.layout');
    }
}
