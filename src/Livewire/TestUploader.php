<?php

namespace ArtflowStudio\FileUploader\Livewire;

use ArtflowStudio\FileUploader\Traits\WithAFUploader;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Comprehensive test component for AF File Uploader.
 *
 * Tests all features: variants, cropper, circle mode, file types, error handling,
 * target-size, convert, optimize, and dark mode.
 */
class TestUploader extends Component
{
    use WithAFUploader;

    // Basic uploads
    public $simpleImage;

    public $simpleVideo;

    public $simpleDocument;

    // Cropper uploads
    public $cropperSquare;

    public $cropperWide;

    public $cropperCircle;

    // Variant tests
    public $variantPlain;

    public $variantSquared;

    public $variantRect;

    public $variantCircled;

    public $variantInline;

    // Error test
    public $errorTest;

    // Advanced features
    public $targetSizeTest;

    public $convertTest;

    public $optimizedTest;

    // Custom size tests
    public $customWidth;

    public $customHeight;

    public function render(): View
    {
        return view('af-uploader::livewire.test-uploader')
            ->layout('af-uploader::test-uploader');
    }
}
