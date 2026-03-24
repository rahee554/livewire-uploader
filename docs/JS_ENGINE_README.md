# AF Cropper & Uploader V3.1 - Professional Suite

A high-performance, attribute-driven vanilla JS cropper with deep Livewire integration, physical unit support, and advanced freeform manipulation.

##  New in V3.1
- **Freeform Resizing**: Edge handles allow manual mask resizing in "Free" mode.
- **Circle/Square Toggle**: Instant switch between circular and square masks for profile pictures.
- **Mobile First Design**: Fully responsive UI with glassmorphism and touch-optimized controls.
- **Pinch-to-Zoom & Drag**: Intuitive multi-touch support for mobile devices.
- **Processing States**: Visual feedback during image export to ensure responsive UX.
- **Minimalist Iconography**: Sleek, clean SVG icons for all editor controls.

##  New in V3.0
- **Physical Unit Support**: Specify `data-af-width="18mm"` and `data-af-height="22mm"` for precise real-world ratios.
- **Toggleable Preview**: Hide the cropped preview image using `data-af-preview="false"` and show a "Success" state instead.
- **Success Overlays**: Minimalist, animated feedback when images are processed.
- **Clear & Close**: Built-in "times" icon to reset specific dropzones.
- **Ratio Orientation**: One-tap toggle between vertical and horizontal crop ratios.
- **Livewire v3.7.3+ Adapter**: Automatic re-initialization and event bridging for modern Laravel/Livewire apps.

##  Configuration Attributes

| Attribute | Description | Example |
|-----------|-------------|---------|
| `data-af-width` | Target width in physical units (`mm`, `cm`, `in`, `px`). | `18mm` |
| `data-af-height`| Target height in physical units. | `22mm` |
| `data-af-preview`| Set to `false` to hide the thumbnail after cropping. | `false` |
| `data-af-lock-aspect`| Hides ratio selectors and locks the user to the default. | `true` |
| `data-af-quality`| Output quality (0.1 - 1.0). Default is `0.92`. | `0.95` |
| `data-af-ratio` | Default ratio if no physical units are used (e.g., `1`, `16/9`).| `4/3` |

##  Layout & Theme Classes
Apply these to your `.af-dropzone` container:

- `.af-dz-squared`, `.af-dz-rect`, `.af-dz-circled`, `.af-dz-inline`.
- `.af-dz-dark` for the professional minimalist theme.

##  Laravel & Livewire Integration
The suite includes a native Laravel package structure and a Blade component.

### Blade Usage
```html
<x-af-uploader wire:model="photo" variant="circled" ratio="1" isCircle="true" />
```

For full documentation on Laravel integration, see [laravel.md](laravel.md).

The JS adapter listens for `livewire:init` and `morph.updated`. You can also manually trigger success or error states from your backend:

```php
// From Livewire component updated hook
$this->dispatch('af-upload-success', inputId: 'my-input-id');
$this->dispatch('af-upload-error', inputId: 'my-input-id', message: 'File too large');
```

##  Global Event
Listen for result data in your custom JS:
```javascript
window.addEventListener('af-image-cropped', (e) => {
    const { input, blob, file } = e.detail;
    console.log('Processed image for:', input.id);
});
```
