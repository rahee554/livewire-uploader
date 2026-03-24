# Changelog

All notable changes to `artflow-studio/uploader` are recorded here.

---

## [0.1.2] — Unreleased

### Cropper Modal Visibility Fix (Critical)

#### Fixed
- **Cropper modal invisible when uploader is inside a hidden tab** — `#af-modal` has `position: fixed; inset: 0` but `position: fixed` does not escape a `display: none` ancestor. When the uploader was rendered inside a tab container hidden via `x-show` (e.g. `x-show="$wire.contentType === 'image'"`), the modal was trapped and never visible, even with the `active` class applied.

  **Root cause:** `@once` renders the modal alongside the first uploader component in the template. In `AddScreenContent`, the first uploader is the video uploader inside `<div ... x-show="$wire.contentType === 'video'">`. When the Image tab was active (not Video), this parent had `display: none`, making the modal invisible.

  **Fix in `index.js`:**
  1. In `CropperApp` constructor, immediately after acquiring the `#af-modal` reference, move it to `document.body` via `document.body.appendChild(this.modal)`. This is done once and ensures the modal is never inside any conditionally-hidden container.
  2. In `openModal()`, repeat the body-move guard (in case the modal was re-inserted by a Livewire re-render) before adding the `active` class.
  3. In `openModal()`, moved `engine.setImage(img)` into a double `requestAnimationFrame` callback. This ensures the browser has completed at least one full layout + paint cycle after making the modal visible, so the canvas wrapper has real pixel dimensions before `setImage` tries to call `reset()`.

---

## [0.1.1] — Unreleased

### WebP & File Validation

#### Added
- `convert` prop: set `convert="webp"` to auto-convert all uploaded images to WebP before upload (non-cropped images). Cropped images were already exported as WebP.
- `lossless` prop: set `lossless="true"` to use lossless WebP compression (quality = 1.0).
- `isTypeAccepted()` helper on the Alpine component — validates a `File` against an `accept` attribute string (handles MIME types, wildcards `image/*`, and extensions `.jpg`).
- `convertToWebp()` async helper on the Alpine component — converts any browser-displayable image to WebP via an off-screen Canvas, with fallback to the original file on error.
- File type validation in `onInputFileChange()` — shows `handleError('Unsupported file type: EXT')` instead of silently doing nothing when a disallowed file type is selected or dragged in.
- File type validation in `onExternalFileSelected()` — same protection for the AF_Cropper drag-and-drop path.

#### Changed
- Default crop quality lowered from `0.92` → `0.80` (WebP at 80% is visually lossless for typical screen content and roughly 30% smaller).
- `quality` prop now defaults to `null` (unset). The effective default is computed in PHP: `0.80` when `convert` is set, `1.0` when `lossless="true"`, `0.92` otherwise.
- `confirmCrop()` no longer reads the unused `ds.afFormat` attribute; format is always `image/webp`. Lossless mode (`data-af-lossless="true"`) sets quality to `1.0`.

---

## [0.1.0] — 2026-03-24

### Initial publishable release

This version consolidates all development work from the prototype phase into a stable, production-ready package.

#### Added
- `WithAFUploader` PHP trait with Livewire 4-compatible property management
- Stable deterministic instance IDs (replaces fragile `uniqid()` approach)
- `storeAFUpload()` — stores temp upload + cleans up `.json` metadata file
- `revertUpload()` — discards temp upload + cleans up `.json` metadata file
- `removeUpload()` — removes permanently stored file from disk
- `dispatchUploadSuccess()` / `af-upload-error` event support
- `AssetUpdateCommand` artisan command (`af-uploader:update-assets`)
- `@afUploaderAssets` Blade directive
- Test components: `TestUploader`, `TabsUploaderTest`, `UploaderTest1–6`
- PHPUnit test suite with feature tests for component rendering, isolation, and asset publishing
- Docs folder with Architecture, JavaScript, Integration, Changelog, and Audit

#### Changed
- Package renamed from `artflow-studio/file-uploader` → `artflow-studio/uploader`
- PHP minimum requirement raised from `^8.1` → `^8.2`
- Laravel requirement narrowed from `^10|^11|^12` → `^11|^12`
- Livewire requirement narrowed from `^3|^4` → `^4`
- Replaced `LivewireManager::updateProperty()` (LW3) with direct property assignment (`$this->{$property} = $value`) throughout `WithAFUploader`
- Fixed duplicate if/elseif branches in `revertUpload()` — both branches did identical work
- Renamed trait class `WithAFuploader` → `WithAFUploader` (consistent PascalCase)
- Updated `UploaderTest1–6` to use `WithAFUploader` instead of bare `WithFileUploads`
- Removed empty `register()` method from `FileUploaderServiceProvider`
- Removed unused `validateAFUpload()` stub method
- Removed unused `setAFPropertyValue()` helper (replaced by direct assignment)
- Removed `LivewireManager` import from trait

---

## [Pre-release Development History]

### 2026-01-23 — v2 Refactor (Instance Isolation + UI)

**Issues fixed:**
- Multiple uploaders on same page shared Alpine state ("LOADED" status appeared in all instances)
- File previews leaked across component boundaries
- UI was boxy/squared with inconsistently sized icons
- Component UI broke on Livewire morph / page refresh
- File dialog opened automatically on page load (5+ times) — critical bug

**Solutions implemented:**
- Unique instance IDs scoped per event `@af-status-update-{id}.window`
- `wire:key` + `wire:ignore` for Livewire morph survival
- Drag-drop logic moved inline to Alpine (removed external JS dependency for this)
- FilePond-inspired glassmorphic preview cards with image thumbnails
- Circular progress spinner with percentage readout
- CSS keyframe animations: `af-fade-in`, `af-pop`, `af-pulse`, `af-spin`
- Clear button repositioned outside dropzone (always accessible, not clipped)
- `syncInitialState()` restores preview from existing Livewire model value

### Initial Development — JS Engine

**Implemented:**
- `CanvasEngine`: HTML5 Canvas rendering, pointer-based zoom/pan/rotate
- `ExportEngine`: OffscreenCanvas → Blob pipeline with iterative compression
- `ImageLoader`: `createImageBitmap()` async decode
- `TransformEngine`: gesture math for pinch-to-zoom
- `LivewireAdapter`: `livewire:init`, `livewire:navigated`, `morph.updated` hooks
- Circle masking (visual overlay + exported mask)
- Physical unit support for print-precision crop dimensions (`mm`, `cm`, `in`)
- `wire:navigate` / Turbolinks compatible re-initialisation
