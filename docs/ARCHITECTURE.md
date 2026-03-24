# Architecture & Design Decisions

> `artflow-studio/uploader` v0.1.0

---

## Overview

The package is structured as a standard Laravel package with auto-discovery. It provides a single opinionated Blade component (`<x-af-uploader>`) that integrates directly with Livewire's file upload system via a companion PHP trait (`WithAFUploader`).

---

## Package Structure

```
packages/artflow-studio/file-uploader/
├── src/
│   ├── FileUploaderServiceProvider.php   — Laravel auto-discovery / asset publishing
│   ├── Console/
│   │   ├── AssetUpdateCommand.php        — Checks published assets for staleness
│   │   └── TestCommand.php              — Dev utility
│   ├── Livewire/
│   │   ├── TestUploader.php             — Comprehensive demo component
│   │   ├── TabsUploaderTest.php         — Tab-persistence demo component
│   │   └── UploaderTest1–6.php          — Minimal single-feature test components
│   └── Traits/
│       └── WithAFUploader.php           — PHP trait for Livewire components
├── resources/
│   └── views/
│       ├── components/
│       │   ├── uploader.blade.php       — PRIMARY component (active)
│       │   ├── uploader-v2.blade.php    — Experimental v2 (random IDs, deprecated)
│       │   └── scripts.blade.php        — Standalone script include partial
│       └── livewire/
│           ├── test-uploader.blade.php  — Full test page view
│           └── tabs-uploader-test.blade.php
├── public/
│   ├── css/
│   │   └── main.css                     — All component styles + CSS variables
│   └── js/
│       ├── index.js                     — Entry point; initialises CropperApp
│       ├── CanvasEngine.js              — Canvas rendering, zoom/pan/rotate
│       ├── ExportEngine.js              — OffscreenCanvas → Blob export pipeline
│       ├── ImageLoader.js               — createImageBitmap decoder
│       ├── LivewireAdapter.js           — Bridges JS ↔ Livewire lifecycle
│       ├── TransformEngine.js           — Pointer-event gesture handling
│       └── Uploader.js                  — XHR upload with progress events
├── tests/
│   ├── TestCase.php                     — Orchestra Testbench base
│   ├── UploaderTest.php                 — Package-level smoke tests
│   └── Feature/
│       ├── UploaderComponentTest.php    — Livewire component feature tests
│       ├── AssetsTest.php               — Asset publishing tests
│       ├── InstanceIsolationTest.php    — Single-component isolation tests
│       └── MultiInstanceIsolationTest.php — Multi-instance event isolation
├── docs/                                — Consolidated documentation
├── composer.json
└── phpunit.xml
```

---

## Core Components

### 1. `<x-af-uploader>` Blade Component

**File:** `resources/views/components/uploader.blade.php`

The primary interface. It is responsible for:

- Generating a **stable, deterministic instance ID** based on `wire:model`, labels, request URI, and loop context (not random, survives Livewire morphs).
- Rendering a **drag-and-drop dropzone** with variant classes (`plain`, `squared`, `rect`, `circled`, `inline`).
- **Initialising Alpine.js state** via `window.afUploader({ ... })` — this function is defined in the JavaScript layer.
- Wiring file input events to either the **cropper pipeline** or the **direct upload pipeline**.
- Rendering the **cropper modal** exactly once per page via `@once`.

**Instance ID strategy (stable hash):**
```php
$context = [
    $modelName,          // wire:model value
    $attributes->get('label', ''),
    $attributes->get('variant', ''),
    $attributes->get('wire:key', ''),
    $attributes->get('name', ''),
    isset($loop) ? $loop->index : '',
    md5(__FILE__),       // differentiates same-property in different views
    request()->getRequestUri(),
];
$stableHash = substr(md5(serialize($context)), 0, 12);
$instanceId = 'af-upl-' . ($attributes->get('id') ?: $stableHash);
```

This approach ensures the ID does **not** change between Livewire re-renders (unlike `uniqid()`), preventing the Alpine component from being re-initialised unnecessarily.

---

### 2. `WithAFUploader` Trait

**File:** `src/Traits/WithAFUploader.php`

A Livewire trait that extends `WithFileUploads` with proper cleanup methods. The key problem it solves: Livewire's `WithFileUploads` does **not** delete the associated `.json` metadata file when a temporary upload is discarded or moved, causing stale files to accumulate in `storage/app/livewire-tmp/`.

**Public API:**

| Method | Purpose |
|--------|---------|
| `removeUpload(string $property, string $filename)` | Remove a permanently stored file from disk |
| `revertUpload(string $property, string $filename)` | Discard a temporary upload + clean up its `.json` |
| `storeAFUpload(TemporaryUploadedFile, string $path, string $disk, ?string $name)` | Store permanently + clean up temp |
| `dispatchUploadSuccess(string $inputId)` | Notify JS layer of a completed store |

**Livewire 4 compatibility:** All property mutations use direct assignment (`$this->{$property} = $value`) — `LivewireManager::updateProperty()` from LW3 is not used.

---

### 3. JavaScript Engine

**Entry:** `public/js/index.js` → `CropperApp`

```
CropperApp
├── CanvasEngine         — renders image on <canvas>, handles zoom/pan/rotate state
├── ExportEngine         — OffscreenCanvas export → Blob → File
├── LivewireAdapter      — hooks livewire:init, livewire:navigated, morph.updated
└── Uploader (implicit)  — $wire.upload() via Alpine x-data
```

**Alpine component factory** (`window.afUploader`): Each `<x-af-uploader>` element receives its own Alpine data object via `x-data="window.afUploader({ id, wireModel, ... })"`. This pattern ensures complete state isolation.

**Event flow (normal upload):**
```
1. User selects / drops file
2. Alpine: onInputFileChange → $wire.upload(property, file, onSuccess, onError, onProgress)
3. Livewire: stores file in livewire-tmp/, returns TemporaryUploadedFile path
4. Alpine: sets hasFile=true, shows preview card
5. Optional: component calls storeAFUpload() to move to permanent storage
```

**Event flow (with cropper):**
```
1. User selects image
2. AF_Cropper intercepts → opens modal
3. User crops/zooms/confirms
4. ExportEngine → Blob → File
5. af-image-cropped-{instanceId} dispatched to window
6. Alpine: onImageCropped → $wire.upload(...)
7. Same as steps 3–5 above
```

---

### 4. Instance Isolation

Every event dispatched from JS includes the instance ID:
```javascript
window.dispatchEvent(new CustomEvent(`af-status-update-${instanceId}`, { detail: { ... } }));
```

Alpine listens only to its own namespaced events:
```blade
@af-status-update-{{ $instanceId }}.window="onStatusUpdate($event)"
```

This pattern prevents state bleed across multiple uploaders in the same component or page.

---

## Data Flow Diagram

```
Browser                          Alpine (per-instance)            Livewire (PHP)
  │                                     │                              │
  │ — select file ─────────────────────>│                              │
  │                                     │— $wire.upload(prop, file) ──>│
  │                                     │<── progress (0→100) ─────────│
  │                                     │<── onSuccess(tmpPath) ────────│
  │                                     │ (hasFile=true, show preview) │
  │                                     │                              │
  │ — remove click ────────────────────>│                              │
  │                                     │— $wire.call('revertUpload') >│
  │                                     │<── property = null ──────────│
  │                                     │ (hasFile=false, reset UI)    │
```

---

## Design Principles

1. **Zero random state**: Instance IDs are deterministic to survive Livewire morphs.
2. **Minimal PHP footprint**: The trait delegates to Livewire's existing upload pipeline.
3. **No external JS dependencies**: No jQuery, no FilePond, no Dropzone. Alpine + vanilla JS only.
4. **Metadata cleanup**: Every temporary file operation cleans up the companion `.json` file.
5. **Wire:navigate support**: The JS engine re-scans for inputs on `livewire:navigated`.
