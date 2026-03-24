# Integration Guide

> `artflow-studio/uploader` v0.1.0 — Laravel 12 · Livewire 4

---

## Installation

### 1. Composer

**From Packagist (once published):**
```bash
composer require artflow-studio/uploader
```

**Local path repository (development):**
```json
// composer.json (root)
"repositories": [
    { "type": "path", "url": "packages/artflow-studio/file-uploader" }
],
"require": {
    "artflow-studio/uploader": "@dev"
}
```

### 2. Publish Assets

```bash
php artisan vendor:publish --tag=af-uploader-assets
```

This copies `public/js/*` and `public/css/main.css` to `public/vendor/af-uploader/`.

### 3. Add Assets to Layout

In your primary Blade layout, before `</head>` and **after** `@livewireStyles`:

```blade
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
```

> **Note:** The CSRF meta tag is required for Livewire file uploads to authenticate correctly.

---

## The `WithAFUploader` Trait

Add the trait to any Livewire component that uses `<x-af-uploader>`:

```php
use ArtflowStudio\FileUploader\Traits\WithAFUploader;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class UploadProfile extends Component
{
    use WithAFUploader;  // includes WithFileUploads automatically

    public $avatar;

    public function save(): void
    {
        $this->validate(['avatar' => ['required', 'image', 'max:2048']]);

        $path = $this->storeAFUpload($this->avatar, 'avatars');
        // Save $path to database...
    }

    public function render(): View
    {
        return view('livewire.upload-profile');
    }
}
```

### Trait Methods

#### `storeAFUpload(TemporaryUploadedFile $file, string $path, string $disk = 'public', ?string $name = null): string`

Store a temporary Livewire upload permanently and clean up both the temp file and its `.json` metadata.

```php
// Auto-named file
$path = $this->storeAFUpload($this->photo, 'photos');

// Custom filename
$path = $this->storeAFUpload($this->photo, 'photos', 'public', 'profile.webp');
```

#### `revertUpload(string $property, string $filename): void`

Discard a **temporary** (not yet permanently stored) upload. Called automatically from JS's remove button via `wire:call`.

```php
// Typically called from Blade via the JS layer:
// $wire.call('revertUpload', 'avatar', filename)
```

#### `removeUpload(string $property, string $filename): void`

Remove a **permanently stored** file from disk and clear the property.

```php
// For existing files already in public storage
$this->removeUpload('avatar', $this->avatar);
```

#### `dispatchUploadSuccess(string $inputId): void`

Dispatch `af-upload-success` to notify the frontend that a server-side store completed.

```php
public function updatedAvatar(): void
{
    $path = $this->storeAFUpload($this->avatar, 'avatars');
    auth()->user()->update(['avatar' => $path]);
    $this->dispatchUploadSuccess('avatar-uploader');
}
```

---

## Blade Component Props

```blade
<x-af-uploader
    wire:model="photo"

    {{-- File filtering --}}
    accept="image/*"
    :max-size="5"

    {{-- Appearance --}}
    variant="plain"
    label="Drop photo here"
    width="100%"
    height="200px"

    {{-- Cropper --}}
    cropper="true"
    ratio="16/9"
    is-circle="false"
    :max-width="2000"
    :quality="0.92"

    {{-- Behaviour --}}
    auto-upload="true"
    multiple="false"
    preview="true"
/>
```

### Full Props Reference

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `wire:model` | string | **required** | Livewire property name |
| `accept` | string | `image/*` | MIME type filter (e.g. `video/*`, `.pdf,.doc`) |
| `max-size` | int | `10` | Max file size in **MB** |
| `label` | string | `Drop file or click` | Dropzone placeholder text |
| `variant` | string | `plain` | Layout: `plain` · `squared` · `rect` · `circled` · `inline` |
| `width` | string | `null` | CSS width (e.g. `200px`, `100%`) |
| `height` | string | `null` | CSS height (e.g. `180px`) |
| `cropper` | string | `false` | Enable built-in image editor |
| `ratio` | string | `null` | Crop ratio: `16/9` · `4/3` · `1/1` · `3/2` |
| `is-circle` | string | `false` | Circular crop mask + circular preview |
| `max-width` | int | `2000` | Maximum exported image width in px |
| `quality` | float | `0.92` | JPEG/WebP output quality (0.0–1.0) |
| `target-size` | string | `null` | Target file size (e.g. `500KB`, `1MB`) — iterative compression |
| `convert` | string | `null` | Force output format: `webp` · `jpeg` · `png` |
| `optimized` | string | `false` | Enable Squoosh-style multi-pass optimisation |
| `preview` | string | `true` | Show file preview card after upload |
| `auto-upload` | string | `true` | Upload automatically on file selection |
| `multiple` | bool | `false` | Allow multiple file selection |

---

## Variants

```blade
{{-- Full-width, uses inline height --}}
<x-af-uploader wire:model="banner" variant="plain" height="200px" />

{{-- Enforced square aspect ratio --}}
<x-af-uploader wire:model="thumbnail" variant="squared" />

{{-- 16:9 aspect ratio --}}
<x-af-uploader wire:model="cover" variant="rect" />

{{-- Circular — perfect for avatars --}}
<x-af-uploader
    wire:model="avatar"
    variant="circled"
    cropper="true"
    is-circle="true"
    width="160px"
    height="160px"
/>

{{-- Compact horizontal layout --}}
<x-af-uploader wire:model="attachment" variant="inline" />
```

---

## Multiple Instances

Multiple uploaders work independently on the same page — each has a stable, isolated instance ID:

```blade
<div class="grid grid-cols-3 gap-6">
    <x-af-uploader wire:model="featuredImage" label="Featured Image" />
    <x-af-uploader wire:model="galleryImage1" label="Gallery 1" />
    <x-af-uploader wire:model="galleryImage2" label="Gallery 2" />
</div>
```

In loops, always add `wire:key` for proper Livewire morphing:

```blade
@foreach ($slides as $slide)
    <x-af-uploader
        wire:model="slides.{{ $loop->index }}.image"
        wire:key="slide-upload-{{ $loop->index }}"
        label="Slide {{ $loop->iteration }}"
    />
@endforeach
```

---

## Server-Side Events

#### Success callback from PHP
```php
// After permanently storing the file
$this->dispatchUploadSuccess('my-input-id');
```

#### Error callback from PHP
```php
$this->dispatch('af-upload-error', inputId: 'my-input-id', message: 'File too large');
```

#### Listening in JavaScript
```javascript
window.addEventListener('af-upload-success', (e) => {
    console.log('Uploaded successfully for input:', e.detail.inputId);
});
```

---

## Asset Management

### Check for stale published assets

```bash
php artisan af-uploader:update-assets
```

### Force re-publish

```bash
php artisan vendor:publish --tag=af-uploader-assets --force
```

---

## Troubleshooting

### Assets not loading
Run `php artisan vendor:publish --tag=af-uploader-assets --force` and ensure `@afUploaderAssets` is present in the layout.

### Upload fails with 419 (CSRF)
Ensure `<meta name="csrf-token" content="{{ csrf_token() }}">` exists in `<head>`.

### Multiple instances sharing state
All versions from `v0.1.0` onwards use deterministic instance IDs. If you observe state leakage, check that each uploader has a distinct `wire:model` property.

### Preview not restoring after page navigation
Use `wire:navigate` with `@persist` on the layout, or ensure `syncInitialState()` has access to a non-null `wire:model` value.

### File dialog opens automatically
Do not call `input.click()` during Alpine `init()`. Only trigger it on explicit user interaction (the dropzone `@click` handler manages this).

### `laravel-tmp` directory filling up
Use `storeAFUpload()` instead of `$file->store()`. The trait method deletes both the temp blob and its companion `.json` metadata file.
