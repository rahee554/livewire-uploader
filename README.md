# ArtFlow Uploader

A Livewire file uploader with a built-in image editor, client-side re-encoding,
and genuine per-instance isolation.

```blade
<x-af-uploader wire:model="photo" editor ratio="16/9" convert="webp" />
```

- **Crop, rotate, zoom** in a canvas editor that opens over the page.
- **Re-encodes before uploading** — convert to WebP, cap the dimensions, or aim
  for a target file size. The network only ever carries the final bytes.
- **Respects EXIF orientation**, so portrait phone photos do not crop sideways.
- **Server-side validation** is the enforcement boundary; the browser checks are
  a convenience.
- **Temporary files are cleaned up**, including the `.json` sidecar Livewire
  leaves behind.
- **No build step.** Plain ES modules and one stylesheet.

Requires PHP 8.2+, Laravel 11 or 12, Livewire 3 or 4.

---

## Install

```bash
composer require artflow-studio/uploader
php artisan af-uploader:install
```

Add the assets to your layout `<head>`:

```blade
@afUploaderAssets
```

Add the trait to any Livewire component that holds an upload:

```php
use ArtflowStudio\FileUploader\Concerns\WithUploader;

class ProfileForm extends Component
{
    use WithUploader;

    public $photo;

    public function save()
    {
        $path = $this->storeUpload($this->photo, 'avatars');
    }
}
```

To customise defaults:

```bash
php artisan vendor:publish --tag=af-uploader-config
```

---

## Props

Every prop falls back to `config/af-uploader.php`, so set your defaults once and
override per uploader.

| Prop | Type | Default | What it does |
| --- | --- | --- | --- |
| `wire:model` | string | — | The bound Livewire property. Required for uploads. |
| `accept` | string | `image/*` | MIME families (`video/*`), types (`application/pdf`) or extensions (`.csv`). |
| `max-size` | number | `10` | Megabytes. `0` disables the check. |
| `max-files` | number | `null` | Cap for `multiple`. |
| `multiple` | bool | `false` | Accept several files at once. |
| `auto-upload` | bool | `true` | `false` shows an Upload button instead. |
| `label` | string | `Drop file or click` | Placeholder text. |
| `variant` | string | `plain` | `plain` · `squared` · `rect` · `circled` · `inline` |
| `width` / `height` | string | `null` | Bare numbers become pixels; units pass through. |
| `preview` | bool | `true` | Show a thumbnail once a file is picked. |
| `show-savings` | bool | `false` | Report `1.4 MB → 220 KB (−84%)` after re-encoding. |

### Image processing

| Prop | Type | Default | What it does |
| --- | --- | --- | --- |
| `convert` | string | `null` | `webp` · `jpeg` · `png`. `null` keeps the source format. |
| `quality` | number | `0.82` | `0`–`1`, or `0`–`100` as a percentage. |
| `lossless` | bool | `false` | Pins quality to 1. |
| `max-width` | number | `2000` | Downscale beyond this width. |
| `max-height` | number | `null` | Downscale beyond this height. |
| `target-size` | string | `null` | Absolute budget: `500KB`, `1.5MB`. |
| `compress` | string | `null` | Relative budget: `40%` — keep at most this share of the original. |

#### Two ways to cap the size

`target-size` is an absolute ceiling; `compress` is a share of whatever was
picked. Give either, or both — when both are set the **tighter one applies**, so
this keeps thumbnails small without bloating an already-small file:

```blade
<x-af-uploader wire:model="photo" convert="webp" compress="50%" target-size="300KB" />
```

A 4MB photo is held to 300KB. A 200KB photo is held to 100KB.

The encoder trades quality first and resolution second — if quality alone
cannot reach the budget it steps the dimensions down and tries again. When even
that falls short, `show-savings` marks the badge amber and its tooltip names the
budget it could not reach, rather than reporting the shortfall as a success.

### Editor

| Prop | Type | Default | What it does |
| --- | --- | --- | --- |
| `editor` | bool | `false` | Open the crop editor for images. (`cropper` is accepted as an alias.) |
| `ratio` | string | `null` | `16/9`, `4:3`, `1`, or `free`. |
| `is-circle` | bool | `false` | Circular mask. Pins the ratio to 1:1. |
| `lock-ratio` | bool | `false` | Hide the ratio buttons. |

Booleans accept `true`/`false`, `"true"`/`"false"`, `1`/`0`, or the bare
attribute (`<x-af-uploader multiple />`).

---

## Examples

**Avatar** — circular crop, compressed under 200KB:

```blade
<x-af-uploader
    wire:model="avatar"
    editor
    is-circle
    variant="circled"
    target-size="200KB"
/>
```

**Gallery** — up to eight images, converted to WebP:

```blade
<x-af-uploader wire:model="gallery" multiple max-files="8" convert="webp" height="220" />
```

With `multiple`, the dropzone becomes a scrollable tile grid. Each tile carries
its own remove button and releases only that file server-side; the caption shows
the count and combined size.

**Attachment** — a single row inside a form:

```blade
<x-af-uploader
    wire:model="attachment"
    variant="inline"
    accept=".pdf,.docx"
    max-size="25"
    label="Attach a document"
/>
```

**Deliberate upload** — nothing leaves the browser until the button is pressed:

```blade
<x-af-uploader wire:model="scan" auto-upload="false" show-savings />
```

---

## Server API

`WithUploader` adds five methods.

### `storeUpload($file, $directory = null, $disk = null, $name = null, $accept = null, $maxSize = null): string`

Validates, then writes the file and releases the temporary copy. Returns a path
relative to the disk root.

```php
$path = $this->storeUpload($this->photo, 'avatars', accept: 'image/*', maxSize: 5);
```

Throws `UploadRejected` when the file fails validation — catch it to show the
message, or let your handler render it.

### `storeUploads($files, ...): array`

The batch form. Returns `[$storedPaths, $rejectionMessages]`; a bad file is
skipped rather than aborting the batch.

```php
[$paths, $errors] = $this->storeUploads($this->gallery, 'gallery');
```

### `discardUpload(string $property, ?string $filename = null): void`

Drops a pending upload and deletes its temporary file. The component calls this
itself when someone clears the dropzone, so `livewire-tmp` does not fill up.

### `deleteStoredUpload(string $property, string $path, ?string $disk = null): bool`

Deletes a file that was already persisted. `$path` must be disk-relative;
absolute paths, URLs and traversal segments are refused.

### `purgeTemporaryFile(TemporaryUploadedFile $file): void`

`protected`. Deletes a temporary upload *and* its `.json` sidecar — Livewire
writes both but only removes the first.

---

## Browser events

Dispatched from the uploader's root element, so `@af-uploader:uploaded` on any
ancestor will catch them.

| Event | Payload |
| --- | --- |
| `af-uploader:uploaded` | `{ id, model }` |
| `af-uploader:failed` | `{ id, model, error }` |
| `af-uploader:cleared` | `{ id, model }`, plus `file` when a single tile was removed |

To clear an uploader from outside it, dispatch `af-uploader:clear` at it:

```blade
<button @click="$dispatch('af-uploader:clear')">Reset</button>
```

---

## Styling

Every class is namespaced `af-*`, and the palette is custom properties. Override
them on your own `:root`:

```css
:root {
    --af-accent: #7c3aed;
    --af-radius: 16px;
}
```

All motion is disabled under `prefers-reduced-motion: reduce`.

### Dark mode

Nothing to configure. The uploader reads whichever convention your application
already uses, on `<html>` or `<body>`, and follows it live:

| Framework | What it sets |
| --- | --- |
| Tailwind (class strategy) | `.dark` |
| Bootstrap 5.3 | `data-bs-theme="dark"` / `"light"` |
| DaisyUI, Flowbite | `data-theme="dark"` / `"light"` |
| Primer | `data-color-mode` |
| Others | `data-mode`, `data-color-scheme`, `.dark-mode`, `.theme-dark` |

When your app expresses no preference, the operating system setting decides. A
theme toggle is picked up as soon as it runs — no page reload, no wiring.

This is resolved in JavaScript rather than CSS on purpose. Tailwind expresses
light mode as the *absence* of `.dark`, which to a stylesheet is
indistinguishable from an app that never configured theming — so a plain
`prefers-color-scheme` rule turns the uploader dark inside light pages on a
dark-mode machine. The resolved answer is written to `data-af-theme` on
`<html>`, and that attribute is the only thing the stylesheet keys off.

To override, set `theme` in `config/af-uploader.php`:

```php
'theme' => 'auto',   // follow the host, then the OS  (default)
'theme' => 'system', // ignore the host, follow the OS only
'theme' => 'light',  // always light
'theme' => 'dark',   // always dark
```

Or drive it from your own toggle at runtime:

```js
window.AFUploaderTheme.set('dark');   // 'dark' | 'light' | 'system' | 'auto'
window.AFUploaderTheme.resolved;      // 'dark' | 'light'
```

---

## Testbed

In `local` and `testing`, `/af-uploader/testbed` renders every configuration on
one page — useful for checking a theme override or reproducing a bug. Control it
with `af-uploader.testbed.enabled`; set it to `false` to remove the route
entirely.

---

## Commands

| Command | Purpose |
| --- | --- |
| `af-uploader:install` | Publish assets and print the setup steps. |
| `af-uploader:publish` | Re-publish CSS and JS. Run after every package upgrade. |

---

## Upgrading from 0.1.x

The old API still works. `ArtflowStudio\FileUploader\Traits\WithAFuploader`
forwards to the new trait, and `storeAFUpload()`, `revertUpload()` and
`removeUpload()` remain as deprecated aliases.

Two things need attention:

1. **Re-publish the assets.** The layout changed from `css/main.css` +
   `js/index.js` to `css/uploader.css` + `js/uploader.js`.
   `php artisan af-uploader:publish --force` reports the stale files.
2. **`auto-upload="false"` now works.** It never did before, so any uploader
   that passed it will start waiting for the button.

See `AUDIT.md` for what changed and why.

---

## Contributing

Issues and pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md)
for how to get set up and what makes a report actionable. Security problems go
through [SECURITY.md](SECURITY.md) rather than a public issue.

<https://github.com/rahee554/livewire-uploader>

## License

MIT. See [LICENSE](LICENSE).
