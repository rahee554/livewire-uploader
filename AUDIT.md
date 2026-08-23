# Package Audit — `artflow-studio/uploader`

> Audited: 2026-08-24 · version at audit time: `0.1.0`
> Scope: every file in the repository, read in full.
> Verified against: PHP 8.4 · Laravel 12.56 · Livewire 4

---

## Executive summary

The package works in the happy path, and its CSS, canvas engine and export engine
are genuinely good. Everything around them is not.

**Roughly 40% of the repository is dead weight** — an abandoned prototype under a
namespace that was never autoloaded, a superseded component version referenced by
nothing, three JavaScript modules that are never imported, a standalone HTML demo,
a test-application scaffold shipped inside `src/`, and ten point-in-time status
reports in `docs/` that contradict each other.

Of what remains, the central problem is architectural: **two independent runtimes
drive the same `<input type="file">`**. Alpine (inlined as a 30 KB `<script>` in a
Blade partial) binds `@change` and `@click`; `AF_Cropper.setupDropzone()`
separately binds *native* `change`, `click`, `dragover` and `drop` on the same
elements. The two halves communicate through global `window` CustomEvents
namespaced by an instance id. Six of the defects below are direct consequences of
that single decision.

**5 critical · 7 high · 8 medium** defects are catalogued here, each with a file
and line reference.

---

## 1. What is actually live

| Layer | Live files | Notes |
| --- | --- | --- |
| Provider | `src/FileUploaderServiceProvider.php` | view namespace `af-uploader`, `<x-af-uploader>`, `@afUploaderAssets`, 2 demo Livewire components, 2 demo routes, 2 commands |
| PHP API | `src/Traits/WithAFuploader.php` | the entire server-side API — five methods |
| Blade | `components/uploader.blade.php` (18 KB) + `components/scripts.blade.php` (30 KB) | 48 KB of Blade; `scripts.blade.php` is one Alpine object literal inside a `@once` script tag |
| JS | `public/js/index.js` (23 KB) + `CanvasEngine` + `ExportEngine` + `LivewireAdapter` | cropper only |
| CSS | `public/css/main.css` (27 KB) | properly scoped `--af-*` tokens; dark mode keyed on `body.dark` |

Everything else in the tree is unreferenced.

---

## 2. Dead weight

| Path | Size | Why it is dead |
| --- | --- | --- |
| `laravel/` (2 files) | 2.4 KB | Abandoned prototype. Namespace `AF\Uploader` is **absent from the PSR-4 map** — never autoloaded, under any code path. |
| `resources/views/components/uploader-v2.blade.php` | 20 KB | Referenced by nothing. Calls `afUploaderInstance_{id}()`, a function that is never defined anywhere. Uses `uniqid()` for `wire:key`. Emits a duplicate `:class` attribute. |
| `public/js/TransformEngine.js` | 5.4 KB | Never imported. See HIGH-01 — it is the *better* implementation. |
| `public/js/ImageLoader.js` | 1.0 KB | Never imported. |
| `public/js/Uploader.js` | 2.2 KB | Never imported, and declares no methods. |
| `html/` (3 files) | 15 KB | Standalone demo plus a second `package.json`. |
| `src/Livewire/UploaderTest1-6.php` | 4.5 KB | Demo scaffold shipped inside `src/`. |
| `resources/views/test-*.blade.php`, `resources/views/livewire/*` | 26 KB | Demo pages shipped to consumers. |
| 10 of 19 `docs/*.md` | 66 KB | Status reports — see below. |

### Documentation

Ten of nineteen documents are point-in-time status reports rather than reference
material: `FINAL_SUMMARY`, `FIX_COMPLETE_REPORT`, `REFACTOR_COMPLETE`,
`REFACTOR_COMPLETE_V2`, `QUICK_REFERENCE`, `ANALYSIS_AND_FIXES`,
`BUGS_FOUND_PLAYWRIGHT`, `process`, `plan`, `JS_ENGINE_README`. They disagree with
each other about what the package does.

`docs/AUDIT.md` (the previous audit) lists HIGH-02 (trait casing), MED-01
(`uploader-v2`) and MED-02 (`laravel/`) among its findings and marks them
resolved. **None of the three were actually fixed.** Treat that document as
aspirational, not descriptive.

`README.md` is 21 KB and contains **two separate prop tables** (lines 119 and 467)
which disagree with one another and with the code.

---

## 3. Critical defects

### CRIT-01 — `auto-upload="false"` has never worked

`resources/views/components/uploader.blade.php:13` defaults the prop to the
**string** `'true'`. Line 103 then renders:

```blade
autoUpload: {{ $autoUpload ? 'true' : 'false' }},
```

In PHP the string `'false'` is truthy, so the expression yields `true` for every
input a user can supply. The prop is inert.

### CRIT-02 — the instance id is not stable across Livewire updates

`uploader.blade.php:41` feeds `request()->getRequestUri()` into the hash that
becomes `wire:key`:

```php
// This keeps ID stable across Livewire morphs while being unique per page
request()->getRequestUri(),
```

During a Livewire update the request URI is `/livewire/update`, not the page URL.
The key produced on re-render therefore differs from the key produced on initial
render — the comment asserts precisely the opposite of what the code does. Line 38
adds `md5(__FILE__)`, a compile-time constant that contributes no entropy.

### CRIT-03 — cross-component state bleed through a globally shared cache

`scripts.blade.php:386` and `:477` write to a page-global object keyed by the
*Livewire property name*:

```js
const cacheKey = this.wireModel || this.id;
window._afUploaderCache[cacheKey] = { filePreview, fileName, hasFile };
```

Two different Livewire components on one page that both bind `wire:model="photo"`
overwrite each other's preview and filename. The package's headline claim is
"perfect instance isolation". The cache is additionally never cleared on
navigation, so it retains revoked `blob:` URLs and restores stale previews after a
`wire:navigate` round trip.

### CRIT-04 — `isImageFile` is declared twice in one object literal

`scripts.blade.php:48` and `scripts.blade.php:585`. JavaScript keeps the last
definition, so the first — the `accept`-aware implementation that also handles
`/livewire/preview-file/` URLs — is silently discarded at parse time. All of its
logic is unreachable.

### CRIT-05 — trait filename does not match its class name

The file is `src/Traits/WithAFuploader.php`; the trait inside it is
`WithAFUploader`. PHP resolves class names case-insensitively, so this survives on
Windows and macOS. Composer's PSR-4 loader resolves *file paths* case-sensitively,
so on Linux any consumer writing `use ...\Traits\WithAFUploader` gets a fatal
"trait not found". The package's own test suite imports it both ways
(`tests/Feature/MultiInstanceIsolationTest.php:3` uses the lowercase spelling).

---

## 4. High-severity defects

### HIGH-01 — the good interaction engine is unused; the inline copy leaks listeners

`TransformEngine.js` implements pan, pinch, wheel-zoom and handle-resize on
unified Pointer Events. It is never imported. `index.js:172-325` reimplements the
same behaviour with separate mouse and touch code paths.

Worse, `initUI()` is re-invoked on `livewire:navigated`, `livewire:init` **and**
`turbo:load`, and each invocation calls `addEventListener` for `mousemove`,
`touchstart`, `touchmove` and `touchend` again without ever removing the previous
handlers. Listeners accumulate across navigations and drag distance multiplies.
Only the `.onwheel` and `.onmousedown` assignments are idempotent.

### HIGH-02 — possible infinite click recursion

`index.js:365-369`:

```js
const triggerClick = (e) => {
    if (e.target.closest(".af-clear-btn") || ...) return;
    if (e._afHandled) return;
    e._afHandled = true;
    input.click();
};
```

`input` is a descendant of `dz`, so the synthetic click bubbles straight back into
the same listener as a **new event object** carrying no `_afHandled` flag, which
calls `input.click()` again.

### HIGH-03 — cancelling an upload does not cancel it

`scripts.blade.php:565` flips UI flags and clears the input. The `$wire.upload()`
request keeps running to completion, and its success callback then overwrites the
"Upload cancelled" message with "Success". There is no `AbortController` and no
call to Livewire's own `cancelUpload`.

### HIGH-04 — removing a file orphans the temporary upload

`remove()` sets the entangled model to `null` and does nothing else. The trait
already ships `revertUpload()` for exactly this purpose, but no JavaScript path
ever calls it, so `storage/app/livewire-tmp` grows without bound — the precise
problem the trait was written to solve.

### HIGH-05 — the Blade-to-JavaScript attribute contract is broken in both directions

| Emitted by Blade, never read | Read by JS, never emitted |
| --- | --- |
| `data-af-target-size` | `afMaxSize` — *this is why `target-size` does nothing* |
| `data-af-optimized` | `afWidth` / `afHeight` (physical-unit ratios) |
| `data-af-preview` | `afLockAspect` |
| `data-af-wire-model` | |

Three documented features — `target-size`, `optimized` and physical-unit aspect
locking — cannot function.

### HIGH-06 — the `convert` prop is ignored by the cropper

`index.js:583` reads `ds.afConvert` into `convertFormat`, then hardcodes
`format: 'image/webp'` on the following line. `convertFormat` is never read again.
`convert="jpeg"` and `convert="png"` are documented but unreachable.

### HIGH-07 — `.json` uploads are blocked for every consumer

`scripts.blade.php:413` rejects any file whose name ends in `.json`. This is a
workaround for Livewire's temporary-metadata quirk that leaked into user-facing
behaviour: a general-purpose uploader that can never accept a JSON file.

---

## 5. Medium-severity defects

- **Boolean props are strings, inconsistently.** `cropper`, `isCircle` and
  `lossless` are compared with `=== 'true'`, so `:cropper="true"` silently fails;
  `multiple` accepts both forms. Casting happens ad hoc at each use site.
- **Silent degradation.** When `@afUploaderAssets` has not been published,
  `window.AF_Cropper` is undefined and `cropper` quietly becomes a plain upload
  with no console warning and no visible difference until the user tries to crop.
- **`alert()` on export failure** (`index.js:600`) — unacceptable in a library.
- **No EXIF orientation handling.** Photographs from phones crop rotated.
  `ImageLoader.js`, which is dead, was moving toward `createImageBitmap`.
- **`ExportEngine` target-size search can terminate above the target.** The
  five-step bisection returns the most recently computed blob rather than the last
  one measured under the limit.
- **A hardcoded `400ms` in `closeModal`** must stay in sync with a CSS transition
  it cannot observe; `closeModal` also omits the null check that `openModal` has.
- **Unscoped global selectors.** `document.querySelectorAll("[data-ratio]")` and
  the ids `af-modal`, `af-canvas`, `af-confirm` collide with any host markup using
  the same names.
- **Dark mode keyed on `body.dark`.** Tailwind places `.dark` on `<html>`;
  Bootstrap 5 uses `data-bs-theme`. Neither is supported.
- **Dead modal controls in JS.** Handlers are bound for `af-free-controls`,
  `af-ratio-flip`, `af-free-w-inc`, `af-free-w-dec`, `af-free-h-inc` and
  `af-free-h-dec`; none of those elements exist in the Blade modal.

---

## 6. Packaging and tests

- **No `config/` file exists.** Nothing is configurable — not the disk, the
  default quality, the size ceiling, nor the storage path.
- **`composer.json`**: `illuminate/support: "*"` is unbounded;
  `livewire/livewire: "^4.0"` excludes Livewire 3; `"version": "0.1.0"` is
  hardcoded where git tags belong; and there is **no `require-dev` at all**, so
  neither Testbench nor PHPUnit is available and the suite cannot run standalone.
- **No `.gitignore`, `phpunit.xml`, CI workflow or contributor guide.**
- **The test suite does not pass.** `tests/UploaderTest.php` sets properties
  `file`, `photo_a` and `photo_b`, none of which exist on `TestUploader` (whose
  properties are `simpleImage`, `cropperSquare`, and so on). `AssetsTest` and
  `MultiInstanceIsolationTest` extend the **host application's** `Tests\TestCase`,
  while `tests/TestCase.php` extends Testbench — the two are incompatible.
- **`AssetUpdateCommand`** hardcodes a seven-file manifest that includes all three
  dead modules, and drifts the moment a file is added or removed.
- **`TestCommand`** calls `Artisan::call('test')`, running the *consuming
  application's* entire test suite from a package command.
- **Demo routes and Livewire components ship to consumers.** They are gated to
  `local` and `testing`, but they belong in a workbench, not in `src/`.

---

## 7. Security

Validation is **client-side only**. `maxSize` and `accept` are enforced in Alpine
and are trivially bypassed by posting directly to Livewire's upload endpoint. The
trait offers no validation helper, no MIME sniffing and no extension allowlist,
and `storeAFUpload()` defaults to the **`public` disk** without checking that the
disk exists.

`removeUpload()` builds a filesystem path from caller-supplied input:

```php
$relativePath = Str::after($filename, config('app.url'));
// ...
File::delete(public_path($relativePath));
```

`$filename` arrives from the browser as a Livewire action argument. This warrants
a hard look for path traversal before the method survives into a 1.0 release.

---

## 8. Verdict

Carry forward: `public/css/main.css`, `CanvasEngine.js`, `ExportEngine.js`, and
the temporary-file cleanup logic inside `WithAFUploader`.

Rebuild rather than patch: the dual Alpine/native event system, the global
`window` event bus, the property-name-keyed cache, and the 30 KB inline
`<script>`. Those four decisions produce CRIT-02, CRIT-03, CRIT-04, HIGH-01,
HIGH-02 and HIGH-05 between them, and no amount of local patching removes the
class of defect.

### Target shape

```
src/         Providers/ · Support/ (Options, Validator) · Concerns/WithUploader.php
config/      uploader.php                    <- new, publishable
resources/   views/components/  (markup only, no inline script)
             js/                             <- real source
dist/        built, versioned assets
workbench/   demo application (Testbench)    <- all demo components move here
tests/       Feature/ · Unit/
docs/        5 reference documents
```

Principles for the rebuild:

1. **One options pipeline.** PHP config merged with component props, serialised
   once as JSON onto the root element. No `data-af-*` side channel.
2. **One event owner per input.** The cropper is invoked as a promise-returning
   function, not addressed through global window events.
3. **A deterministic instance id** derived from the bound property and a
   per-render counter, never from the request URI.
4. **Server-side validation** as the enforcement boundary; the client-side checks
   remain, as a convenience only.

---

## 9. Remediation log

All findings above are addressed. The package test suite (79 tests) and a
browser pass over `/af-uploader/testbed` back the entries marked *verified*.

### Critical

| ID | Resolution | Verified by |
| --- | --- | --- |
| CRIT-01 | `Options::bool()` treats the string `'false'` as false. Every prop goes through it. | `OptionsTest`, and the testbed's manual panel now genuinely holds the file back |
| CRIT-02 | `Support/InstanceId` derives the id from the Livewire component id plus a per-render ordinal. `Support/InstanceIdHook` resets the ordinals at the start of every render, so they reproduce under Octane too. | `InstanceIdTest`, `LivewireIntegrationTest::ids_are_identical_on_re_render` |
| CRIT-03 | The page-global `_afUploaderCache` is gone. All state lives on the Alpine instance. | Clearing one uploader in the browser leaves its neighbour untouched |
| CRIT-04 | One `isImageFile`, in `core/files.js`. | — |
| CRIT-05 | Canonical trait is `Concerns\WithUploader`. `Traits/WithAFuploader.php` remains as a deprecated shim, its filename matching the spelling consumers import, and is eagerly loaded via `autoload.files` so PSR-4 path casing never matters. | `WithUploaderTest::the_deprecated_trait_still_works` |

### High

| ID | Resolution | Verified by |
| --- | --- | --- |
| HIGH-01 | `editor/PointerController` — the orphaned TransformEngine, on Pointer Events, registered once and released by `destroy()`. | — |
| HIGH-02 | `@click.stop` on the file input stops the synthetic click bubbling back to the dropzone. **This one recurred in the rewrite and was caught in the browser**, not by the test suite: one click was opening two file dialogs. | Browser — one click, one dialog |
| HIGH-03 | `cancel()` calls `$wire.cancelUpload()`. | — |
| HIGH-04 | `clear()` calls `discardUpload()` on the server. | Browser — the temp file *and* its `.json` sidecar disappear from `livewire-tmp` |
| HIGH-05 | The `data-af-*` channel is deleted. One options object, built by `Support/Options`, serialised once into `x-data`. | `UploaderComponentTest::no_stale_data_attribute_channel_remains` |
| HIGH-06 | `convert` drives the encoder and the editor's output format. | `OptionsTest`, and the browser produced a `.webp` from a `.png` source |
| HIGH-07 | The `.json` block is gone. The extension allowlist is config, server-side. | — |

### Medium

| Finding | Resolution |
| --- | --- |
| Inconsistent boolean casting | One `Options::bool()`, applied to every prop. |
| Silent degradation when assets are missing | The editor is a dynamic import; a failure surfaces in the dropzone rather than doing nothing. |
| `alert()` in library code | Errors resolve through the promise and render in the dropzone. |
| No EXIF orientation | `decode()` uses `createImageBitmap(..., { imageOrientation: 'from-image' })`. |
| Target-size search could overshoot | Both search loops keep the best under-budget blob. |
| Hardcoded 400ms close timeout | Removed; the modal toggles a class. |
| Unscoped global selectors | The editor owns its DOM and queries within its own root. |
| `body.dark` only | `prefers-color-scheme`, `.dark`, `[data-theme]`, `[data-bs-theme]` and `body.dark`. |
| Dead modal controls | Gone. |

### Packaging

`config/uploader.php` added · constraints bounded, Livewire 3 and 4 · `require-dev`
with Testbench, PHPUnit and Pint · `.gitignore` and `phpunit.xml` added · 79
passing tests replacing a suite that could not run · `af-uploader:publish` walks
the asset directory instead of a hardcoded manifest, and names orphaned files ·
demo components moved out of `src/` into a single testbed gated to local and
testing.

One packaging finding is **deliberately not fixed**: `composer.json` still
carries `"version": "0.1.0"`. The repository has no git tags, and it is consumed
as a path repository against a `^0.1.0` constraint, so removing the field would
break resolution. Tag the repository and delete the field together.

### Security

| Finding | Resolution |
| --- | --- |
| Client-side-only validation | `Support/UploadValidator` runs inside `storeUpload()`: size ceiling, accept matching, and an extension blocklist checked against the client filename, the guessed extension, and every segment of a double extension. SVG is blocked unless explicitly allowed. |
| Path traversal in `removeUpload()` | `deleteStoredUpload()` normalises the input to a disk-relative path and refuses absolute paths, URLs pointing elsewhere, null bytes and `..` segments. |

### Found during the rebuild

Two defects that were not in the original audit, both caught by exercising the
package rather than reading it:

- **`slots` is a reserved property name in Livewire 4.** A public `array $slots`
  on a component collides with the framework's own slot storage and fails with
  `Call to a member function getName() on null` during dehydration.
- **`@entangle` cannot be used inside an anonymous Blade component.** It compiles
  against `$__livewire`, which such a component never has in scope.
  `Support/LivewireBridge` builds the same expression from
  `ExtendBlade::currentRendering()` and degrades to a plain input when rendered
  outside Livewire.

Separately, Livewire 4's wire-key precompiler cannot handle a `@foreach` inside
an *inline* `render()` string — a bare loop with no package markup fails
identically. Loop coverage therefore uses a real view file.
