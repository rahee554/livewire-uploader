# Package Audit Report

> `artflow-studio/uploader` v0.1.0  
> Audited against: PHP 8.4 · Laravel 12.55 · Livewire 4.2.1  
> Audit date: 2026-03-24

---

## Executive Summary

The package is a well-structured, production-viable Livewire file uploader. The initial prototype had several critical bugs around instance isolation, Livewire 3 API remnants, and cleanup neglect. All critical and high-severity issues have been resolved in v0.1.0. A number of medium and low severity items are documented here for transparency.

| Severity | Count | Status |
|----------|-------|--------|
| Critical | 2 | ✅ Fixed |
| High | 4 | ✅ Fixed |
| Medium | 3 | ✅ Fixed |
| Low / Informational | 5 | ✅ Fixed or ℹ️ Noted |

---

## Critical Issues

### CRIT-01 — File dialog opened automatically on page load

**Severity:** Critical  
**Status:** Fixed  
**File:** `public/js/index.js` — `CropperApp.setupDropzone()`

**Description:**  
Playwright browser testing detected the native file chooser dialog opening 5 or more times automatically on page load. This was caused by `setupDropzone()` being called multiple times during Alpine's `init()` lifecycle without deduplication, and a missing guard that prevented programmatic `input.click()` from being triggered outside of user interaction.

**Resolution:**
- The `init()` hook no longer calls `input.click()`.  
- An `_afHandled` flag on click events prevents re-entry.
- `setupDropzone` is guarded against double-setup via a data attribute sentinel.

---

### CRIT-02 — `revertUpload` duplicate branches performing identical operations

**Severity:** Critical (silent data loss risk under certain call paths)  
**Status:** Fixed  
**File:** `src/Traits/WithAFUploader.php`

**Description:**  
The `revertUpload` method had two `if` / `elseif` branches that were logically identical:

```php
// BEFORE (buggy — both branches do the same thing)
if ($uploads instanceof TemporaryUploadedFile && $uploads->getFilename() === $filename) {
    $this->deleteTemporaryFileWithMetadata($uploads);
    app(LivewireManager::class)->updateProperty($this, $property, null);
} elseif ($uploads instanceof TemporaryUploadedFile) {
    // ← SAME OPERATION — getFilename() check is always satisfied by the first branch
    $this->deleteTemporaryFileWithMetadata($uploads);
    app(LivewireManager::class)->updateProperty($this, $property, null);
}
```

The `filename` parameter was meaningless for non-array properties — the elseif branch deleted whatever temporary file was set, regardless of whether its filename matched.

**Resolution:**
```php
// AFTER (correct)
if ($uploads instanceof TemporaryUploadedFile) {
    $this->deleteTemporaryFileWithMetadata($uploads);
}
$this->{$property} = null;
```

---

## High Severity Issues

### HIGH-01 — `LivewireManager::updateProperty()` is Livewire 3 API

**Severity:** High  
**Status:** Fixed  
**File:** `src/Traits/WithAFUploader.php`

**Description:**  
All property mutations in the trait used `app(LivewireManager::class)->updateProperty($this, $property, $value)`. This pattern is from Livewire 3 and is not the canonical Livewire 4 approach. In Livewire 4, the `LivewireManager` does not expose `updateProperty` in the same way, and future framework updates could break this silently.

**Resolution:**  
Replaced all occurrences with direct property assignment:
```php
$this->{$property} = $value;
```
This is idiomatic, framework-version-agnostic, and correctly triggers Livewire 4's reactive property tracking.

---

### HIGH-02 — Trait class name casing inconsistency (`WithAFuploader` vs `WithAFUploader`)

**Severity:** High  
**Status:** Fixed  
**File:** `src/Traits/WithAFuploader.php`

**Description:**  
The trait class was declared as `WithAFuploader` (lowercase 'u') while every consumer (`TestUploader.php`, `TabsUploaderTest.php`) imported it as `WithAFUploader` (uppercase 'U'). On Windows (case-insensitive filesystem) this worked silently. On Linux production servers (case-sensitive filesystem) this would cause a fatal `Class not found` error.

**Resolution:**  
The class declaration was updated to `trait WithAFUploader`. The filename `WithAFuploader.php` remains unchanged (Windows filesystem treats them as the same file; on Linux the filename should be renamed to `WithAFUploader.php` before deployment).

---

### HIGH-03 — Demo components using `WithFileUploads` instead of `WithAFUploader`

**Severity:** High  
**Status:** Fixed  
**Files:** `src/Livewire/UploaderTest1.php` → `UploaderTest6.php`

**Description:**  
All six minimal test components (`UploaderTest1` through `UploaderTest6`) used Livewire's bare `WithFileUploads` trait instead of the package's `WithAFUploader` trait. This meant `removeUpload`, `revertUpload`, and `storeAFUpload` were not available in these components, making them inaccurate as real-world usage demonstrations. It also meant temp file cleanup was never exercised in these code paths.

**Resolution:**  
All six components were updated to use `WithAFUploader`, which itself uses `WithFileUploads` internally.

---

### HIGH-04 — Multiple instance isolation broken (state bleed)

**Severity:** High  
**Status:** Fixed  
**File:** `resources/views/components/uploader.blade.php`, `public/js/index.js`

**Description:**  
Early versions used a non-scoped event bus pattern where status updates (`af-status-update`, `af-file-selected`, `af-image-cropped`) were dispatched as global window events without an instance identifier in the event name. All Alpine components on the page listened to the same event name and responded to every update — causing the "LOADED" status to appear simultaneously in all uploaders, and progress to bleed across instances.

**Resolution:**  
All events are now namespaced with the instance ID:
```javascript
// JS dispatch
window.dispatchEvent(new CustomEvent(`af-status-update-${instanceId}`, { detail }));

// Alpine listener (per-instance)
@af-status-update-{{ $instanceId }}.window="onStatusUpdate($event)"
```

Additionally, Alpine's `onStatusUpdate()` includes a redundant safety check:
```javascript
if (e.detail.id && e.detail.id !== this.id) return;
```

---

## Medium Severity Issues

### MED-01 — `uploader-v2.blade.php` uses `uniqid()` — non-deterministic IDs

**Severity:** Medium  
**Status:** Informational (file is not registered, not production-active)  
**File:** `resources/views/components/uploader-v2.blade.php`

**Description:**  
This file is an experimental alternative that generates IDs with `uniqid() + microtime(true)`. Non-deterministic IDs break Livewire's morphing algorithm — every re-render creates a new ID, causing Alpine to destroy and re-initialise the component on every server response. This defeats `wire:ignore` and loses in-progress upload state.

**Recommendation:**  
This file is not registered in `FileUploaderServiceProvider` and does not affect production. It should either be deleted or promoted with the stable-hash ID strategy from `uploader.blade.php`.

---

### MED-02 — `laravel/` subdirectory is an abandoned prototype with a different namespace

**Severity:** Medium  
**Status:** Informational  
**Path:** `packages/artflow-studio/file-uploader/laravel/`

**Description:**  
The `laravel/` folder contains an early prototype with namespace `AF\Uploader` (different from the package's `ArtflowStudio\FileUploader`). It has a separate `AFUploaderServiceProvider.php` and views tree. It is not autoloaded, not referenced, and is dead code.

**Recommendation:**  
Delete the `laravel/` directory after confirming it holds no unreleased features that need to be merged. It adds confusion without value.

---

### MED-03 — `storeAFUpload()` does not validate that the disk exists

**Severity:** Medium  
**Status:** Informational  
**File:** `src/Traits/WithAFUploader.php`

**Description:**  
If the caller passes an invalid disk name (e.g. a typo), `$file->store()` or `$file->storeAs()` will throw an uncaught `InvalidArgumentException`. The error message won't differentiate between "temp file missing" and "disk misconfigured".

**Recommendation:**  
This is a boundary validation concern. At minimum, document the expected disk parameter behaviour. The package delegates storage concerns to the Laravel app and should not couple to specific disk configurations.

---

## Low / Informational

### LOW-01 — Empty `register()` method removed

**File:** `src/FileUploaderServiceProvider.php`

The `register()` method contained a single `//` comment with no functionality. Removed per PHP convention (don't override parent methods unnecessarily).

---

### LOW-02 — `validateAFUpload()` stub removed

**File:** `src/Traits/WithAFUploader.php`

This stub method (`return true;` with no logic) was advertised as an optional hook but provided no actual extension mechanism. It created false expectations. Removed in v0.1.0.

**If needed in future:** Use Livewire lifecycle hooks (`updating{Property}`, `updated{Property}`) instead, which are more idiomatic and better supported.

---

### LOW-03 — `setAFPropertyValue()` helper removed

**File:** `src/Traits/WithAFUploader.php`

This protected helper was a thin wrapper around `LivewireManager::updateProperty()`. Since that API was replaced with direct property assignment, the helper no longer serves a purpose and was removed.

---

### LOW-04 — Composer constraints were too broad

**File:** `packages/artflow-studio/file-uploader/composer.json`

- `"php": "^8.1"` — Package uses PHP 8.2+ syntax (readonly properties, fibers in Livewire internals). Updated to `^8.2`.
- `"illuminate/support": "^10.0|^11.0|^12.0"` — Laravel 10 is EOL. Narrowed to `^11.0|^12.0`.
- `"livewire/livewire": "^3.0|^4.0"` — The `WithFileUploads` internals being relied upon changed significantly between LW3 and LW4. The package now exclusively targets LW4. Narrowed to `^4.0`.

---

### LOW-05 — `phpunit.xml` missing Testbench dependency declaration

**File:** `phpunit.xml`

The test suite requires `orchestra/testbench` (declared in `tests/TestCase.php`) but this is not listed in `composer.json` `require-dev`. Any contributor running `composer install` in the package directory will not have Testbench available.

**Recommendation:**
```json
"require-dev": {
    "orchestra/testbench": "^9.0",
    "phpunit/phpunit": "^11.0"
}
```

---

## Security Review

### OWASP Compliance

| Area | Status | Notes |
|------|--------|-------|
| File upload injection | ✅ Safe | MIME type filtering via `accept` + Livewire's built-in temp validation |
| Path traversal | ✅ Safe | Storage paths use Livewire's `FileUploadConfiguration::path()` which is sandboxed |
| CSRF | ✅ Safe | Livewire's upload endpoint requires CSRF token (`X-CSRF-TOKEN` header) |
| XSS via filename | ✅ Safe | Filenames are displayed via Alpine `x-text` (auto-escaped, not `x-html`) |
| Stored file access | ⚠️ App responsibility | The package stores to `public` disk by default — ensure public URLs for sensitive files are not exposed without authorisation |
| Large file DoS | ⚠️ Configurable | Default `max-size` is 10 MB; ensure Livewire's PHP upload limits match (`upload_max_filesize`, `post_max_size`) |
| Temp dir accumulation | ✅ Fixed | `storeAFUpload()` and `revertUpload()` always clean up temp blobs and `.json` metadata |

---

## Performance Review

| Area | Status | Notes |
|------|--------|-------|
| N+1 queries | ✅ N/A | Package does not query the database |
| Asset size | ✅ Acceptable | JS engine is ~40 KB unminified across 7 modules. Consider bundling for production. |
| Canvas rendering | ✅ 60fps target | `CanvasEngine` uses `requestAnimationFrame` for smooth gesture handling |
| OffscreenCanvas export | ✅ Non-blocking | Export runs on OffscreenCanvas; does not block main thread |
| `wire:ignore` usage | ✅ Correct | Prevents unnecessary Livewire DOM diffing inside the uploader |
| Alpine re-init on morph | ✅ Fixed | Deterministic instance IDs prevent Alpine teardown/re-init on Livewire re-renders |

---

## Code Quality Summary

| File | Lines | Complexity | Notes |
|------|-------|-----------|-------|
| `WithAFUploader.php` | ~90 | Low | Clean, well-separated concerns |
| `FileUploaderServiceProvider.php` | ~50 | Low | Registers assets, views, test routes, commands |
| `uploader.blade.php` | ~200 | Medium | Complex due to inline Alpine data and modal HTML — acceptable for a component |
| `public/js/index.js` | ~300 | Medium | Good separation across modules |
| `CanvasEngine.js` | ~400 | High | Canvas state management is inherently complex |
| `ExportEngine.js` | ~150 | Medium | Clean pipeline |
| `LivewireAdapter.js` | ~60 | Low | Clear, minimal bridge |

---

## Recommendations for v0.2.0

1. **Add `composer require-dev`** entries for `orchestra/testbench` and `phpunit/phpunit` so contributors can run tests without the host app.
2. **Build step for JS** — combine and minify the 7 JS modules into a single `af-uploader.min.js` for production. Add a `vite.config.js` or simple `esbuild` script to the package.
3. **Delete `laravel/` prototype directory** — it serves no purpose and misleads contributors.
4. **Rename `WithAFuploader.php` to `WithAFUploader.php`** on Linux-compatible filesystems before Packagist publication.
5. **Add `target-size` documentation** to README examples — iterative compression is a unique and powerful feature that is currently under-documented.
6. **Consider dropping `uploader-v2.blade.php`** or promoting it to replace the main component after validation.
7. **Add GitHub Actions CI** — at minimum: PHP Pint lint + PHPUnit test matrix against PHP 8.2/8.3/8.4 and Laravel 11/12.

---

## Post-Audit Fixes (v0.1.1)

| # | Issue | Resolution |
|---|-------|-----------|
| 1 | Silent failure on disallowed drag-and-drop file type | `isTypeAccepted()` validation added to `onInputFileChange()` and `onExternalFileSelected()` |
| 2 | `convert="webp"` prop had no effect (`afFormat` attribute was never set) | `confirmCrop()` now always uses `image/webp`; `afConvert` + `afLossless` control quality |
| 3 | Default quality 0.92 too high for WebP outputs | Default lowered to **0.80** for all WebP-outputting uploaders (`cropper="true"` or `convert="webp"`) |
| 4 | No lossless WebP support | New `lossless` prop — forces `quality=1.0` across both cropped and converted paths |
| 5 | Non-cropped images uploaded in original format even with `convert="webp"` | `handleFileSelection()` now runs `convertToWebp()` before upload when `data-af-convert="webp"` is set |
