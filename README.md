# ArtFlow Uploader

> A production-grade Livewire file uploader with a built-in image editor, progress tracking, and perfect multi-instance isolation.

![Version](https://img.shields.io/badge/version-0.1.0-blue.svg)
![Laravel](https://img.shields.io/badge/Laravel-12%2B-red.svg)
![Livewire](https://img.shields.io/badge/Livewire-4.x-purple.svg)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

---

## Features

- **Drop-in Blade component** — one tag, zero configuration required
- **Five layout variants** — `plain`, `squared`, `rect`, `circled`, `inline`
- **Built-in image editor** — crop, zoom, pan, rotate with multi-ratio support (1:1, 16:9, 4:3, free)
- **Circular crop mode** — perfect for profile picture / avatar workflows
- **Instant file preview** — thumbnail for images, file-type icon for documents and videos
- **Circular progress spinner** — live upload percentage display
- **Perfect instance isolation** — any number of uploaders on the same page, no state bleed
- **Tab persistence** — files persist when switching between tabs in a multi-section form
- **Metadata cleanup** — temp uploads and their `.json` metadata files are always cleaned up
- **`wire:navigate` support** — works in Livewire SPA mode
- **Dark mode ready** — CSS variables, works with Tailwind's `dark:` strategy
- **Mobile first** — drag-and-drop, pinch-to-zoom, touch-optimised controls
- **No extra JS dependencies** — Alpine.js (bundled with Livewire) + vanilla JS only

---

## Requirements

| | Minimum |
|---|---|
| PHP | 8.2 |
| Laravel | 11 or 12 |
| Livewire | 4.x |
| Alpine.js | Bundled with Livewire 4 |

---

## Installation

### 1. Install

```bash
composer require artflow-studio/uploader
```

### 2. Publish assets

```bash
php artisan vendor:publish --tag=af-uploader-assets
```

### 3. Add the directive to your layout

In your main Blade layout, add `@afUploaderAssets` **after** `@livewireStyles`:

```blade
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">   {{-- required --}}

    @livewireStyles
    @afUploaderAssets
</head>
<body>
    {{ $slot }}
    @livewireScripts
</body>
```

---

## Quick Start

### Blade Component

```blade
<x-af-uploader wire:model="photo" />
```

### Livewire Component

```php
use ArtflowStudio\FileUploader\Traits\WithAFUploader;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class UploadPhoto extends Component
{
    use WithAFUploader;

    public $photo;

    public function save(): void
    {
        $this->validate(['photo' => ['required', 'image', 'max:5120']]);

        $path = $this->storeAFUpload($this->photo, 'photos');
        // Persist $path to your database...
    }

    public function render(): View
    {
        return view('livewire.upload-photo');
    }
}
```

---

## Component Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `wire:model` | string | **required** | Livewire property to bind |
| `accept` | string | `image/*` | MIME type filter (`video/*`, `.pdf,.doc`, etc.) |
| `max-size` | int | `10` | Max file size in **MB** |
| `label` | string | `Drop file or click` | Dropzone placeholder text |
| `variant` | string | `plain` | `plain` · `squared` · `rect` · `circled` · `inline` |
| `width` | string | `null` | CSS width e.g. `200px`, `100%` |
| `height` | string | `null` | CSS height e.g. `180px` |
| `cropper` | string | `false` | Enable image editor |
| `ratio` | string | `null` | Crop ratio: `16/9` · `4/3` · `1/1` · `3/2` |
| `is-circle` | string | `false` | Circular mask + circular preview |
| `max-width` | int | `2000` | Maximum export width in px |
| `quality` | float | `0.92` | JPEG/WebP output quality (0–1) |
| `target-size` | string | `null` | Target export size e.g. `500KB` — iterative compression |
| `convert` | string | `null` | Force output format: `webp` · `jpeg` · `png` |
| `preview` | string | `true` | Show file preview after upload |
| `auto-upload` | string | `true` | Upload immediately on file selection |
| `multiple` | bool | `false` | Allow multi-file selection |

---

## Layout Variants

```blade
{{-- Plain — full-width, uses specified height --}}
<x-af-uploader wire:model="banner" variant="plain" height="200px" />

{{-- Squared — enforced square aspect ratio --}}
<x-af-uploader wire:model="thumbnail" variant="squared" />

{{-- Rect — 16:9 aspect ratio --}}
<x-af-uploader wire:model="cover" variant="rect" />

{{-- Circled — perfect for profile pictures --}}
<x-af-uploader
    wire:model="avatar"
    variant="circled"
    cropper="true"
    is-circle="true"
    width="160px"
    height="160px"
/>

{{-- Inline — compact horizontal layout --}}
<x-af-uploader wire:model="attachment" variant="inline" />
```

---

## Image Cropper

```blade
{{-- Basic crop with 16:9 ratio --}}
<x-af-uploader
    wire:model="coverImage"
    cropper="true"
    ratio="16/9"
/>

{{-- Circular avatar with circle mask --}}
<x-af-uploader
    wire:model="avatar"
    variant="circled"
    cropper="true"
    is-circle="true"
    ratio="1/1"
/>

{{-- Free-form crop (no locked ratio) --}}
<x-af-uploader
    wire:model="photo"
    cropper="true"
/>
```

**In-editor controls:** 1:1 · 4:3 · 3:2 · 16:9 · Free · Circle toggle · Rotate left/right · Zoom in/out · Auto-fit

---

## File Types

```blade
{{-- Images only (default) --}}
<x-af-uploader wire:model="photo" accept="image/*" />

{{-- Video --}}
<x-af-uploader wire:model="video" accept="video/*" label="Drop video here" />

{{-- Documents --}}
<x-af-uploader wire:model="document" accept=".pdf,.doc,.docx" label="Drop document" />

{{-- Any file --}}
<x-af-uploader wire:model="file" accept="*/*" />
```

---

## Multiple Instances

Each uploader is fully isolated — you can place as many as needed on a single Livewire component:

```blade
<div class="grid grid-cols-2 gap-6">
    <x-af-uploader wire:model="featured" label="Featured Image" />
    <x-af-uploader wire:model="thumbnail" label="Thumbnail" variant="squared" />
</div>
```

**In loops**, always add `wire:key`:

```blade
@foreach ($slides as $i => $slide)
    <x-af-uploader
        wire:model="slides.{{ $i }}.image"
        wire:key="slide-upload-{{ $i }}"
    />
@endforeach
```

---

## `WithAFUploader` Trait

The trait wraps Livewire's `WithFileUploads` with proper temp-file cleanup.

### `storeAFUpload()`

```php
// Auto-named
$path = $this->storeAFUpload($this->photo, 'photos');

// Custom filename
$path = $this->storeAFUpload($this->photo, 'photos', 'public', 'profile.webp');
```

### Upload hooks

```php
// Server-side success notification → JS shows "Stored Successfully"
$this->dispatchUploadSuccess('my-uploader-id');

// Server-side error notification → JS shows error message
$this->dispatch('af-upload-error', inputId: 'my-uploader-id', message: 'File rejected');
```

### Removing files

```php
// Remove a temporary (not yet stored) upload
$this->revertUpload('photo', $filename);

// Remove a permanently stored file from disk
$this->removeUpload('photo', $storedPath);
```

---

## Artisan Commands

```bash
# Check if published assets are stale vs package source
php artisan af-uploader:update-assets

# Force re-publish
php artisan vendor:publish --tag=af-uploader-assets --force
```

---

## Testing

```bash
# Run package tests
cd packages/artflow-studio/file-uploader
vendor/bin/phpunit
```

The test suite covers:
- Component rendering
- File upload with Storage fake
- File validation (type, size)
- File removal
- Multi-instance independence
- Asset publishing

---

## Customisation

### Override CSS variables

In your `app.css`:

```css
:root {
    --af-primary:  #your-brand-color;
    --af-radius:   12px;
    --af-bg:       #f8fafc;
}
```

### Publish views

```bash
php artisan vendor:publish --tag=af-uploader-views
```

Views are published to `resources/views/vendor/af-uploader/`.

---

## Troubleshooting

| Problem | Solution |
|---------|---------|
| Assets not loading | Run `vendor:publish --tag=af-uploader-assets --force` |
| 419 CSRF error on upload | Add `<meta name="csrf-token">` to `<head>` |
| Multiple uploaders sharing state | Ensure each has a distinct `wire:model` property |
| Preview missing after navigate | Ensure `wire:model` is not null when the component mounts |
| File dialog opens automatically | Do not call `input.click()` in Alpine `init()` |
| `livewire-tmp` accumulating files | Use `storeAFUpload()` — it cleans up the `.json` metadata |

---

## Documentation

| File | Contents |
|------|---------|
| [docs/INTEGRATION.md](docs/INTEGRATION.md) | Full installation, trait API, props reference |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Package structure, design decisions, data flow |
| [docs/JAVASCRIPT.md](docs/JAVASCRIPT.md) | JS engine modules, Alpine integration, CSS variables |
| [docs/CHANGELOG.md](docs/CHANGELOG.md) | Version history and migration notes |
| [docs/AUDIT.md](docs/AUDIT.md) | Complete code audit, findings, and recommendations |

---

## License

MIT © ArtFlow Studio


## ✨ Features

- 🎨 **Beautiful UI**: Modern glassmorphic design with smooth animations
- 📸 **Instant Preview**: Shows thumbnail and filename immediately
- 📐 **Flexible Layouts**: Plain, Squared, Rect, Circled, and Inline variants
- ✂️ **Built-in Cropper**: Mobile-friendly image editor with multi-ratio support
- ⚡ **Livewire 3/4 Optimized**: Seamless integration with latest Livewire
- 🔒 **Perfect Isolation**: Multiple instances work independently
- 🔄 **Tab Persistence**: Files persist when switching tabs
- 🎯 **Zero Config**: Drop in and it works with `wire:model`
- 📱 **Responsive**: Works on desktop, tablet, and mobile
- 🌙 **Dark Mode Ready**: Built-in dark mode support
- 🚀 **Performance Optimized**: Handles large files efficiently
- ⏱️ **Circular Progress**: Beautiful spinner with upload percentage
- 🛡️ **Error Handling**: Auto-reset on upload failures
- 🔗 **wire:navigate Support**: Works with Livewire SPA navigation

## Requirements

- PHP 8.2+
- Laravel 11+ (tested with Laravel 12)
- Livewire 3.x or 4.x
- Alpine.js (included with Livewire)

## Installation

### 1. Install via Composer

```bash
composer require artflow-studio/file-uploader
```

### 2. Publish Assets

```bash
php artisan vendor:publish --tag=af-uploader-assets
```

### 3. Add Assets to Layout

Add the assets directive to your layout (before `</head>`):

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    @livewireStyles
    @afUploaderAssets
</head>
<body>
    {{ $slot }}
    
    @livewireScripts
</body>
</html>
```

> **Important:** The CSRF meta tag is required for file uploads to work.

## Quick Start

### Basic Usage

```blade
<x-af-uploader wire:model="photo" />
```

### In Your Livewire Component

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;

class PhotoUpload extends Component
{
    use WithFileUploads;
    
    public $photo;
    
    public function save()
    {
        $this->validate([
            'photo' => 'required|image|max:10240',
        ]);
        
        $path = $this->photo->store('photos', 'public');
        // Save $path to database...
    }
    
    public function render()
    {
        return view('livewire.photo-upload');
    }
}
```

## Configuration Options

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `wire:model` | string | *required* | Livewire property to bind to |
| `accept` | string | `image/*` | Accepted file types (MIME types) |
| `label` | string | `Drop file or click` | Placeholder label text |
| `variant` | string | `plain` | Layout: `plain`, `squared`, `rect`, `circled`, `inline` |
| `width` | string | `null` | Custom width (e.g., `200px`, `100%`) |
| `height` | string | `null` | Custom height (e.g., `180px`) |
| `max-size` | int | `10` | Maximum file size in MB |
| `auto-upload` | bool | `true` | Auto-upload on file selection |
| `cropper` | string | `false` | Enable image cropper |
| `ratio` | string | `null` | Crop aspect ratio (e.g., `16/9`, `1/1`) |
| `is-circle` | string | `false` | Circular crop mode |
| `preview` | string | `true` | Show file preview |
| `quality` | float | `0.92` | Output image quality (0-1) |
| `max-width` | int | `2000` | Maximum output width in pixels |
| `multiple` | bool | `false` | Enable multiple file upload mode |

## Layout Variants

### Plain (Default)
Full-width responsive layout.
```blade
<x-af-uploader wire:model="file" variant="plain" />
```

### Squared
Perfect square aspect ratio.
```blade
<x-af-uploader wire:model="file" variant="squared" />
```

### Rectangle
16:9 aspect ratio.
```blade
<x-af-uploader wire:model="file" variant="rect" />
```

### Circled (Perfect for Avatars)
Circular display with rounded preview.
```blade
<x-af-uploader 
    wire:model="avatar" 
    variant="circled" 
    cropper="true"
    is-circle="true"
/>
```

### Inline
Compact horizontal layout.
```blade
<x-af-uploader wire:model="file" variant="inline" />
```

## Image Cropping

### Basic Cropper
```blade
<x-af-uploader 
    wire:model="photo" 
    cropper="true" 
    ratio="16/9"
/>
```

### Available Aspect Ratios
- `16/9` - Widescreen
- `4/3` - Standard
- `1/1` - Square
- `3/2` - Classic photo
- Free crop (omit ratio prop)

### Circular Avatar Cropper
```blade
<x-af-uploader 
    wire:model="avatar" 
    cropper="true"
    is-circle="true"
    ratio="1/1"
    variant="circled"
/>
```

## File Type Examples

### Images Only
```blade
<x-af-uploader wire:model="image" accept="image/*" />
```

### Videos Only
```blade
<x-af-uploader wire:model="video" accept="video/*" />
```

### Documents
```blade
<x-af-uploader wire:model="document" accept=".pdf,.doc,.docx,.txt" />
```

### Multiple Types
```blade
<x-af-uploader wire:model="media" accept="image/*,video/*" />
```

## Multiple Instances

Each uploader is completely isolated:

```blade
<div class="grid grid-cols-3 gap-4">
    <x-af-uploader wire:model="cover" label="Cover Image" />
    <x-af-uploader wire:model="avatar" variant="circled" cropper="true" />
    <x-af-uploader wire:model="document" accept=".pdf" />
</div>
```

## Multiple File Upload

Enable multiple file selection with the `multiple` prop:

```blade
<x-af-uploader wire:model="files" multiple accept="image/*" />
```

```php
class Gallery extends Component
{
    use WithFileUploads;
    
    public $files = [];
    
    public function save()
    {
        $this->validate([
            'files.*' => 'image|max:10240',
        ]);
        
        foreach ($this->files as $file) {
            $file->store('gallery', 'public');
        }
    }
}
```

> **Note:** When `multiple` is enabled, the Livewire property should be an array. The uploader uses `$wire.uploadMultiple()` internally.

## Tab Persistence

Files persist when switching between tabs. **You must use `x-show` with `wire:ignore`** — do **not** use `@if` / `@elseif`, which destroys the DOM and loses uploader state.

```php
class ContentWizard extends Component
{
    use WithFileUploads;
    
    public string $activeTab = 'image';
    public $imageFile;
    public $videoFile;
    
    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }
}
```

```blade
<div>
    <button wire:click="switchTab('image')">Images</button>
    <button wire:click="switchTab('video')">Videos</button>
    
    <div wire:ignore x-show="$wire.activeTab === 'image'" x-cloak style="display: none;">
        <x-af-uploader wire:model="imageFile" accept="image/*" />
    </div>
    
    <div wire:ignore x-show="$wire.activeTab === 'video'" x-cloak style="display: none;">
        <x-af-uploader wire:model="videoFile" accept="video/*" />
    </div>
</div>
```

> **Why `x-show` + `wire:ignore`?** Using `@if` removes the uploader from the DOM on tab switch, destroying Alpine state. With `x-show`, the uploaders stay mounted but hidden, preserving all state including uploaded files, previews, and cropper configuration.

## Using the Trait

For convenience, include the trait in your Livewire components:

```php
use ArtflowStudio\FileUploader\Traits\WithAFUploader;

class MyComponent extends Component
{
    use WithAFUploader;
    
    public $photo;
}
```

This trait includes `WithFileUploads` and provides helper methods like:
- `removeUpload($property, $filename)` - Remove an uploaded file
- `revertUpload($property, $filename)` - Revert a temporary upload

## Events

The uploader dispatches these browser events:

| Event | Payload | Description |
|-------|---------|-------------|
| `af-upload-finished` | `{ property, response, id }` | File uploaded successfully |
| `af-upload-finished` | `{ property, response, id, multiple: true, count }` | Multiple files uploaded |
| `af-upload-error` | `{ property, error, id }` | Upload failed |

Listen in Alpine.js:
```blade
<div x-data @af-upload-finished.window="console.log('Uploaded!', $event.detail)">
    <x-af-uploader wire:model="photo" />
</div>
```

## Artisan Commands

### Test Installation
```bash
php artisan af-uploader:test
```
Runs comprehensive tests to verify the package is properly installed.

### Update Assets
```bash
php artisan af-uploader:update-assets
```
Checks if published assets are outdated and updates them.

```bash
php artisan af-uploader:update-assets --force
```
Force republish all assets.

## Test Routes

In `local` environment, the package provides test routes:

| Route | Description |
|-------|-------------|
| `/af-uploader/test` | Comprehensive feature test |
| `/af-uploader/tabs-test` | Tab persistence test |

## Customization

### Publishing Views
```bash
php artisan vendor:publish --tag=af-uploader-views
```

Views will be published to `resources/views/vendor/af-uploader/`.

### CSS Variables

Customize the appearance using CSS variables:

```css
:root {
    --af-bg: #f8f9fa;
    --af-surface: #ffffff;
    --af-border: #e2e8f0;
    --af-primary: #1e293b;
    --af-secondary: #64748b;
    --af-accent: #3b82f6;
    --af-danger: #ef4444;
    --af-success: #10b981;
    --af-radius: 0.75em;
}
```

### Dark Mode
The uploader supports dark mode automatically:
```css
[data-bs-theme="dark"],
.dark {
    --af-bg: #1f1f1e;
    --af-surface: #1f1f1e;
    --af-border: #3f3f3e;
    --af-primary: #f1f0ef;
    --af-secondary: #d3d2d1;
}
```

## Troubleshooting

### "Page Expired" Error
Add the CSRF meta tag to your layout:
```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
```

### Files Not Persisting Between Tabs
1. Use `x-show` with `wire:ignore` instead of `@if` — `@if` destroys the DOM and loses uploader state
2. Use separate Livewire properties for each uploader
3. Ensure the tab content wrapper has `x-cloak style="display: none;"` to prevent flash of hidden content

### Upload Fails Silently
1. Check Laravel logs for validation errors
2. Verify file size is under the limit
3. Ensure storage is linked: `php artisan storage:link`

### Cropper Not Opening
1. Publish assets: `php artisan vendor:publish --tag=af-uploader-assets --force`
2. Check browser console for JavaScript errors
3. Verify Alpine.js is loaded before uploader assets

### Cropper Not Working After wire:navigate
This is fixed in v1.0.0+. The package automatically reinitializes on navigation.

### JSON File Being Uploaded Instead of Actual File
This is fixed in v1.0.0+. The package validates file types before upload.

## Changelog

### v1.1.0
- Added `multiple` prop for multi-file upload support
- Fixed tab persistence: use `x-show` + `wire:ignore` pattern (replaces broken `@if` approach)
- Fixed `autoUpload` property reference (was using closure instead of stored value)
- Removed redundant `initCropper()` call in `init()` (already handled by `reinit()`)
- Added `isResetting` guard to prevent click propagation during file removal
- Added per-file size validation for multiple uploads
- Updated documentation with correct tab persistence patterns

### v1.0.0
- Added circular spinner with upload percentage
- Added auto-reset on upload errors
- Fixed cropper initialization on wire:navigate
- Fixed circle variant showing squared image
- Added 3-layer z-index system for proper layering
- Fixed UI flicker when removing files
- Added comprehensive test routes
- Added `af-uploader:update-assets` command
- Improved dark mode support
- Enhanced error handling

## License

MIT License. See [LICENSE](LICENSE) for details.

## Credits

Built with ❤️ by [ArtFlow Studio](https://artflow.pk)

Inspired by [FilePond](https://pqina.nl/filepond/) and [Spatie Livewire FilePond](https://github.com/spatie/livewire-filepond).
