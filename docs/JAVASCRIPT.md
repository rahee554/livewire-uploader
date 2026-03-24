# JavaScript Engine Reference

> `artflow-studio/uploader` v0.1.0 — `public/js/`

---

## Module Map

| File | Class | Responsibility |
|------|-------|---------------|
| `index.js` | `CropperApp` | Bootstrap, modal UI bindings, input scanning |
| `CanvasEngine.js` | `CanvasEngine` | Canvas rendering, transform state, pointer events |
| `ExportEngine.js` | `ExportEngine` | OffscreenCanvas → Blob → File export pipeline |
| `ImageLoader.js` | `ImageLoader` | `createImageBitmap()` decoder |
| `TransformEngine.js` | `TransformEngine` | Zoom/pan/rotate gesture math |
| `LivewireAdapter.js` | `LivewireAdapter` | Livewire lifecycle hooks + event bridge |
| `Uploader.js` | — | XHR upload helpers (used internally) |

All modules are ES Modules (`type="module"`) and are published to `public/vendor/af-uploader/js/`.

---

## CropperApp (`index.js`)

The singleton entry point. Created once at `DOMContentLoaded` and exposed as `window.AF_Cropper`.

```javascript
window.AF_Cropper = new CropperApp();
```

### Key Methods

| Method | Description |
|--------|-------------|
| `scan()` | Scans DOM for `[af-cropper="true"]` inputs and attaches handlers |
| `setupDropzone(input)` | Attaches file-change listener to a single input |
| `open(file, input)` | Decodes image and opens the crop modal |
| `closeModal(cancelled)` | Closes modal, optionally resets state |
| `confirmCrop()` | Exports the cropped result and dispatches to Alpine |
| `showStatus(input, msg, type)` | Dispatches `af-status-update-{id}` to the window |

### Navigation Support

```javascript
// Supports wire:navigate / Livewire SPA mode
document.addEventListener('livewire:navigated', () => this.reinitializeOnNavigation());
document.addEventListener('livewire:init',      () => this.reinitializeOnNavigation());
document.addEventListener('turbo:load',         () => this.reinitializeOnNavigation());
```

---

## CanvasEngine (`CanvasEngine.js`)

Manages the `<canvas id="af-canvas">` element inside the crop modal.

**State object:**
```javascript
{
    scale:       1.0,     // current zoom level
    offsetX:     0,       // pan offset X
    offsetY:     0,       // pan offset Y
    rotation:    0,       // degrees (0, 90, 180, 270)
    aspectRatio: 1,       // 0 = free, >0 = locked
    isCircle:    false,   // circular mask mode
}
```

**Key capabilities:**
- `setAspectRatio(r)` — switch ratio; flips orientation if user is in portrait mode
- `setCircle(bool)` — toggle circular mask overlay
- `fit()` — auto-fit image to current crop region
- Pointer events: drag to pan, pinch to zoom (pointer distance delta)

---

## ExportEngine (`ExportEngine.js`)

Produces the final exported image from the current canvas state.

```javascript
// Returns Promise<File>
exporter.export(engine.state, imageBitmap, options)
  .then(file => uploader.uploadToLivewire(file, input));
```

**Export options:**
```javascript
{
    maxWidth:   2000,     // px — downscale if source is larger
    quality:    0.92,     // JPEG/WebP quality
    format:     'webp',   // 'webp' | 'jpeg' | 'png'
    targetSize: null,     // bytes — iterative quality reduction
    isCircle:   false,    // apply circular mask before export
}
```

**`targetSize` compression loop:**  
If set, the engine reduces quality in steps of 0.05 until the Blob size is ≤ `targetSize`, with a floor of `quality: 0.1`.

---

## LivewireAdapter (`LivewireAdapter.js`)

Bridges the JavaScript layer with Livewire's lifecycle and event system.

```javascript
// Hooks wired at construction time
document.addEventListener('livewire:init', initHandler);
document.addEventListener('livewire:navigated', () => app.scan());

// On init, subscribes to server-dispatched events
Livewire.hook('morph.updated', ({ el }) => app.scan());
Livewire.on('af-upload-success', ({ inputId }) => app.showStatus(input, "Stored", "success"));
Livewire.on('af-upload-error',   ({ inputId, message }) => app.showStatus(input, message, "danger"));
```

**Why `morph.updated`?** When Livewire re-renders a component, the DOM is morphed in-place. New `<input af-cropper="true">` elements introduced during a morph aren't picked up by the initial `scan()` — this hook re-scans after each morph.

---

## Alpine Integration

The `<x-af-uploader>` component initialises an Alpine data object for each instance:

```javascript
x-data="window.afUploader({
    wireModel: '{{ $wireModel->value() }}',
    modelValue: @entangle($wireModel->value()),
    id:         '{{ $instanceId }}',
    autoUpload: true,
    maxSize:    10,        // MB
    cropper:    false,
    accept:     'image/*',
    multiple:   false
})"
```

The `window.afUploader()` factory returns a plain object with:

| Property | Type | Description |
|----------|------|-------------|
| `progress` | number | Upload progress 0–100 |
| `isUploading` | bool | Upload in-flight flag |
| `statusText` | string | Current user-facing status message |
| `statusType` | string | `success` \| `danger` \| `info` |
| `hasFile` | bool | Whether a file is currently represented |
| `filePreview` | string\|null | URL or path for image thumbnail |
| `fileName` | string | Display name |
| `dragActive` | bool | Drag-over state for CSS class binding |
| `isResetting` | bool | Transient flag during file removal |

---

## CSS Variables

All visual theming is driven by CSS custom properties defined in `public/css/main.css`:

```css
:root {
    --af-primary:     #3b82f6;   /* blue-500 */
    --af-success:     #10b981;   /* emerald-500 */
    --af-error:       #ef4444;   /* red-500 */
    --af-info:        #6366f1;   /* indigo-500 */
    --af-bg:          #ffffff;
    --af-surface:     #f9fafb;
    --af-border:      #e5e7eb;
    --af-text:        #1f2937;
    --af-text-muted:  #6b7280;
    --af-radius:      8px;
    --af-shadow:      0 1px 3px rgba(0,0,0,.1), 0 1px 2px rgba(0,0,0,.06);
}
```

**Dark mode** is applied via `prefers-color-scheme: dark` and via a `.dark` parent class, consistent with Tailwind CSS v4's dark mode strategy.

---

## Feature Roadmap (from `html/feature.md`)

### Implemented
- Physical unit support (`mm`, `cm`, `in`) for print-precision crops
- Iterative compression to hit a target file size
- Circle masking with OffscreenCanvas export
- Freeform resize handles
- 90° rotation with real-time degree display
- Auto-fit (one-click center + fill)
- Livewire v3/v4 adapter with morph support
- Pinch-to-zoom (mobile pointer events)

### Planned
- Brightness / Contrast slider
- Flip horizontal / vertical
- WebWorker offloading for 4K exports
- Multiple file queuing in single modal
- Cloud storage preset integrations
