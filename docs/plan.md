## Goal

Build a mobile-first, client-side Cropper + Uploader that decodes images, enables zoom/pan/rotate, supports aspect ratios and optional circular export, compresses/exports to WebP/JPEG/PNG, and uploads to a Laravel backend (Livewire-compatible) with progress.

All implementation tasks and progress will be tracked in `process.md` in this repository.

## Core Architecture (brief)

- Input: File / Drag & Drop / Paste
- Decode: `createImageBitmap()`
- Canvas Preview: `HTMLCanvasElement` (pointer events)
- Offscreen Export: `OffscreenCanvas` → `toBlob()`
- Transform Engine: zoom / pan / rotate / reset
- Mask: rectangle or circle (visual + export)
- Export: compress & convert to WebP/JPEG/PNG
- Upload: XHR/FormData → Laravel (Livewire temp upload compatible)

## Tech Stack

- Language: ES2024+ (ES Modules)
- UI: plain HTML + CSS (no framework)
- Rendering: Canvas + OffscreenCanvas
- Gestures: Pointer Events API (optional: `@use-gesture/vanilla`)
- Icons / Animations: optional lightweight libs

## Integration Flow (FileUploader → Cropper → Upload)

1. User selects or drops a file into `FileUploader`.
2. `FileUploader` opens the Cropper modal with decoded `ImageBitmap`.
3. User crops, zooms, rotates, toggles circle mode.
4. User confirms; Cropper returns a Blob or File (WebP/JPEG) sized/quality-optimized.
5. Uploader sends Blob as `FormData` via XHR to the Laravel endpoint; progress events update UI.
6. Backend validates, stores temp file (Livewire v3 compatible), moves to storage on finalization.

## Deliverables

- Clean, agent-friendly `plan.md` (this file).
- `process.md`: ordered tasks, milestones, acceptance criteria.
- Minimal prototype scope: ImageLoader, CanvasEngine, TransformEngine, CropMask, ExportEngine, FileUploader integration.
- Integration spec for Livewire uploads (endpoint, payload, headers).

## Acceptance Criteria

- Cropper runs smoothly on mobile (60fps target for gestures) and desktop.
- Exports preserve circle mask when enabled.
- Exported images meet quality/size targets and upload reliably with progress.
- Integration with Livewire temp upload works end-to-end.

## Next Steps (short)

1. See `process.md` for the full ordered checklist and start implementing top-priority items.
2. Implement a minimal prototype scaffold when plan is approved.

---
_Plan optimized for AI-driven execution; `process.md` will be the canonical task tracker._