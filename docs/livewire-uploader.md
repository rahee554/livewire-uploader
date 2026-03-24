# Livewire Uploader Package Documentation

This document explains how to set up the **AF Cropper & Uploader** as a standalone Laravel Livewire package.

## 📦 Package Structure

Your Laravel package should be organized as follows:

```text
af-uploader/
├── src/
│   └── AFUploaderServiceProvider.php  (Registration & Asset Loading)
├── resources/
│   └── views/
│       └── components/
│           └── uploader.blade.php     (Reusable Blade Component)
├── public/
│   ├── index.js                       (Auto-initializing Uploader Engine)
│   ├── CanvasEngine.js
│   ├── ExportEngine.js
│   ├── LivewireAdapter.js
│   └── main.css                       (Styles & Animations)
└── composer.json
```

## 🛠 Advanced Initialization

To ensure the uploader works perfectly with **Livewire Navigate** (SPA mode), the `LivewireAdapter` handles lifecycle hooks automatically.

### Automated Bootstrapping
In `AFUploaderServiceProvider.php`, we register a directive to include assets with the correct versions:

```php
Blade::directive('afUploaderAssets', function () {
    return "
        <link rel='stylesheet' href='{!! asset('vendor/af-uploader/main.css') !!}'>
        <script type='module' src='{!! asset('vendor/af-uploader/index.js') !!}'></script>
    ";
});
```

### JS Lifecycle Hooks
The `src/LivewireAdapter.js` listens for three critical triggers:
1.  `livewire:init`: Initial page load.
2.  `livewire:navigated`: Fired when using Livewire persistent navigation (avoids full reload).
3.  `morph.updated`: Fired when a Livewire component re-renders (ensures new inputs are scanned).

```javascript
document.addEventListener("livewire:init", () => {
   window.AF_Cropper.scan(); // Initial scan
});

document.addEventListener("livewire:navigated", () => {
   window.AF_Cropper.scan(); // Re-scan after SPA navigation
});
```

## ⚙️ Component API

The `<x-af-uploader>` component supports the following parameters:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `wire:model`| String | - | Binding to Livewire property. |
| `cropper` | Boolean| `true` | If false, works as standard uploader. |
| `variant` | String | `squared` | `squared`, `rect`, `circled`, `inline`. |
| `downloadable`| Boolean| `false` | Shows a download icon in the file preview. |
| `maxWidth` | Int | `2000` | Max exported image width. |
| `quality` | Float | `0.92` | Jpeg/WebP quality. |

## 🔄 Server-Side Sync

When a crop is completed, the JS engine triggers `@af-image-cropped`. If `wire:model` is present, it calls Livewire's internal `$wire.upload` with the resulting Blob.

### Handling Progress
The component includes an **Alpine.js** powered progress bar:
```html
<div class="af-progress-bar" :style="'width: ' + progress + '%'"></div>
```
This bar fills in real-time as the file is streamed to the server.

### Storing Files
In your Livewire component, use the `WithFileUploads` trait:

```php
use Livewire\WithFileUploads;

class ProfileSettings extends Component {
    use WithFileUploads;

    public $photo;

    public function updatedPhoto() {
        $this->photo->store('avatars', 'public');
        $this->dispatch('af-upload-success', inputId: 'avatar-input');
    }
}
```
