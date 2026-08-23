import { CanvasEngine } from "./CanvasEngine.js";
import { ExportEngine } from "./ExportEngine.js";
import { PointerController } from "./PointerController.js";
import { decode } from "../core/transcode.js";

/**
 * The crop / rotate / zoom modal.
 *
 * Two deliberate departures from the previous design:
 *
 *  1. **It owns its markup.** The modal used to live in an @once block inside
 *     the Blade component and was addressed by global ids, so it could be
 *     trapped inside a display:none tab panel and collided with any host
 *     element named #af-canvas or #af-confirm. It is now built once, on
 *     demand, as a direct child of <body>.
 *
 *  2. **open() returns a promise.** The old flow dispatched
 *     `af-image-cropped-{id}` on window and hoped the right Alpine instance was
 *     listening. The caller now simply awaits a File, or null if the user
 *     cancelled — no global event bus, no id matching, nothing to leak.
 */
class ImageEditor {
    constructor() {
        this.root = null;
        this.engine = null;
        this.exporter = new ExportEngine();
        this.pointer = null;
        this.session = null;
        this.escapeHandler = null;
    }

    /**
     * @param {File} file
     * @param {{ratio: ?number, circle: boolean, lockRatio: boolean, format: string,
     *          quality: number, maxWidth: ?number, maxHeight: ?number,
     *          targetSize: ?number, ratios: string[]}} options
     * @returns {Promise<File|null>} the cropped file, or null when cancelled.
     */
    async open(file, options = {}) {
        // Only one crop at a time; a second request cancels the first.
        if (this.session) this.finish(null);

        this.build();

        const image = await decode(file);

        return new Promise((resolve, reject) => {
            this.session = { resolve, reject, file, options, image };

            this.applyOptions(options);
            this.root.classList.add("active");
            this.root.setAttribute("aria-hidden", "false");

            this.engine.resize();

            // Give the browser one full layout+paint of the now-visible modal
            // before measuring, or the canvas reports a zero-size wrapper.
            requestAnimationFrame(() => requestAnimationFrame(() => {
                this.engine.resize();
                this.engine.setImage(image);
                this.confirmButton.focus();
            }));
        });
    }

    // ---------------------------------------------------------------- markup

    build() {
        if (this.root) return;

        const root = document.createElement("div");
        root.id = "af-modal";
        root.className = "af-modal";
        root.setAttribute("role", "dialog");
        root.setAttribute("aria-modal", "true");
        root.setAttribute("aria-label", "Image editor");
        root.setAttribute("aria-hidden", "true");
        root.innerHTML = TEMPLATE;

        document.body.appendChild(root);

        this.root = root;
        this.canvasWrapper = root.querySelector(".af-canvas-wrapper");
        this.rotationLabel = root.querySelector(".af-rot-val");
        this.ratioGroup = root.querySelector(".af-ratio-group");
        this.circleToggle = root.querySelector(".af-circle-toggle");
        this.confirmButton = root.querySelector('[data-af="confirm"]');

        this.engine = new CanvasEngine(root.querySelector("canvas"));
        this.engine.onRender = () => this.syncRotationLabel();
        this.pointer = new PointerController(this.engine);

        this.bind();
    }

    bind() {
        const on = (selector, handler) => {
            this.root.querySelectorAll(selector).forEach((el) => el.addEventListener("click", handler));
        };

        on('[data-af="cancel"]', () => this.finish(null));
        on('[data-af="confirm"]', () => this.confirm());
        on('[data-af="rotate-left"]', () => this.engine.rotate(-90));
        on('[data-af="rotate-right"]', () => this.engine.rotate(90));
        on('[data-af="zoom-in"]', () => this.engine.zoom(1.15));
        on('[data-af="zoom-out"]', () => this.engine.zoom(0.87));
        on('[data-af="fit"]', () => this.engine.fitImage());

        this.circleToggle.addEventListener("click", () => {
            this.engine.toggleMask();
            this.circleToggle.classList.toggle("active", this.engine.state.isCircle);
        });

        this.ratioGroup.addEventListener("click", (e) => {
            const button = e.target.closest("[data-ratio]");
            if (!button) return;

            this.selectRatio(button.dataset.ratio, button);
        });

        // Clicking the backdrop cancels, but only the backdrop itself.
        this.root.addEventListener("pointerdown", (e) => {
            if (e.target === this.root) this.finish(null);
        });
    }

    // --------------------------------------------------------------- session

    applyOptions(options) {
        this.renderRatioButtons(options.ratios || DEFAULT_RATIOS);

        const ratio = options.ratio ?? 1;

        this.engine.state.isCircle = Boolean(options.circle);
        this.engine.setAspectRatio(ratio);

        // Reinstate the flag: setAspectRatio clears it for anything but 1:1.
        if (options.circle && ratio === 1) {
            this.engine.state.isCircle = true;
        }

        this.markActiveRatio(ratio);

        const square = Math.abs(ratio - 1) < 0.001;
        this.circleToggle.classList.toggle("visible", square && !options.lockRatio);
        this.circleToggle.classList.toggle("active", Boolean(options.circle));

        this.ratioGroup.style.display = options.lockRatio ? "none" : "";
        this.rotationLabel.textContent = "0°";

        this.escapeHandler = (e) => {
            if (e.key === "Escape") this.finish(null);
        };
        document.addEventListener("keydown", this.escapeHandler);
    }

    renderRatioButtons(ratios) {
        const buttons = ratios
            .map((ratio) => {
                const free = String(ratio).toLowerCase() === "free";
                const label = free ? "Free" : String(ratio).replace("/", ":");
                const value = free ? "0" : ratio;

                return `<button type="button" class="af-btn" data-ratio="${value}">${label}</button>`;
            })
            .join("");

        // The circle toggle sits inside the group, right after the 1:1 button.
        this.ratioGroup.innerHTML = buttons;
        this.ratioGroup.appendChild(this.circleToggle);
    }

    selectRatio(raw, button) {
        const ratio = parseRatio(raw);

        this.engine.setAspectRatio(ratio);

        this.ratioGroup.querySelectorAll("[data-ratio]").forEach((el) => el.classList.remove("active"));
        button?.classList.add("active");

        const square = Math.abs(ratio - 1) < 0.001;
        this.circleToggle.classList.toggle("visible", square);
        if (!square) this.circleToggle.classList.remove("active");
    }

    markActiveRatio(ratio) {
        const buttons = [...this.ratioGroup.querySelectorAll("[data-ratio]")];
        buttons.forEach((el) => el.classList.remove("active"));

        const match = buttons.find((el) => Math.abs(parseRatio(el.dataset.ratio) - ratio) < 0.001);
        (match || buttons[0])?.classList.add("active");
    }

    syncRotationLabel() {
        if (!this.rotationLabel) return;

        const degrees = Math.round(this.engine.state.rotation) % 360;
        this.rotationLabel.textContent = `${degrees}°`;
    }

    async confirm() {
        if (!this.session) return;

        const { options } = this.session;
        const button = this.confirmButton;
        const label = button.textContent;

        button.disabled = true;
        button.textContent = "Processing…";

        try {
            const blob = await this.exporter.export(this.engine, this.engine.getCropArea(), {
                format: options.format || "image/webp",
                quality: options.quality ?? 0.82,
                maxWidth: options.maxWidth ?? null,
                maxHeight: options.maxHeight ?? null,
                targetSize: options.targetSize ?? null,
                circle: this.engine.state.isCircle,
            });

            this.finish(new File([blob], nameFor(this.session.file.name, options.format), {
                type: blob.type,
                lastModified: Date.now(),
            }));
        } catch (error) {
            // Surfaced to the uploader instance, which shows it in the
            // dropzone. The old build called alert() from library code.
            this.finish(error instanceof Error ? error : new Error("The image could not be processed."));
        } finally {
            button.disabled = false;
            button.textContent = label;
        }
    }

    /** @param {File|Error|null} result */
    finish(result) {
        if (!this.session) return;

        const { resolve, reject, image } = this.session;
        this.session = null;

        if (this.escapeHandler) {
            document.removeEventListener("keydown", this.escapeHandler);
            this.escapeHandler = null;
        }

        if (typeof ImageBitmap !== "undefined" && image instanceof ImageBitmap) {
            image.close();
        }

        this.root.classList.remove("active");
        this.root.setAttribute("aria-hidden", "true");
        this.engine.image = null;

        if (result instanceof Error) {
            reject(result);
            return;
        }

        resolve(result);
    }
}

const DEFAULT_RATIOS = ["1", "4/3", "3/2", "16/9", "free"];

function parseRatio(raw) {
    const value = String(raw).trim().toLowerCase();

    if (value === "free" || value === "0") return 0;
    if (value.includes("/") || value.includes(":")) {
        const [w, h] = value.split(/[/:]/).map(Number);
        return h > 0 ? w / h : 1;
    }

    const number = Number(value);

    return Number.isFinite(number) && number > 0 ? number : 1;
}

function nameFor(original, format) {
    const extension = { "image/webp": "webp", "image/jpeg": "jpg", "image/png": "png" }[format] || "webp";
    const base = String(original || "image").replace(/\.[^.]+$/, "") || "image";

    return `${base}-cropped.${extension}`;
}

const TEMPLATE = `
<div class="af-modal-header">
    <span class="af-modal-title">Image editor</span>
    <button type="button" class="af-btn af-modal-close" data-af="cancel" aria-label="Close editor">&times;</button>
</div>

<div class="af-canvas-wrapper">
    <canvas></canvas>
    <div class="af-rot-val" id="af-rot-val" aria-live="polite">0&deg;</div>
</div>

<div class="af-controls">
    <div class="af-ratio-group" role="group" aria-label="Aspect ratio"></div>

    <button type="button" class="af-circle-toggle" title="Toggle circular mask" aria-label="Toggle circular mask">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
    </button>

    <div class="af-btn-group" role="group" aria-label="Transform">
        <button type="button" class="af-btn af-icon-btn" data-af="rotate-left" title="Rotate left" aria-label="Rotate left">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12.25 2c-5.52 0-10 4.48-10 10s4.48 10 10 10 10-4.48 10-10h-2c0 4.42-3.58 8-8 8s-8-3.58-8-8 3.58-8 8-8v4l5-5-5-5v4z"/></svg>
        </button>
        <button type="button" class="af-btn af-icon-btn" data-af="zoom-out" title="Zoom out" aria-label="Zoom out">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M19 13H5v-2h14v2z"/></svg>
        </button>
        <button type="button" class="af-btn af-icon-btn" data-af="fit" title="Fit image" aria-label="Fit image to frame">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
        </button>
        <button type="button" class="af-btn af-icon-btn" data-af="zoom-in" title="Zoom in" aria-label="Zoom in">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        </button>
        <button type="button" class="af-btn af-icon-btn" data-af="rotate-right" title="Rotate right" aria-label="Rotate right">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M11.75 2c5.52 0 10 4.48 10 10s-4.48 10-10 10-10-4.48-10-10h2c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8v4l-5-5 5-5v4z"/></svg>
        </button>
    </div>

    <button type="button" class="af-btn af-btn-primary" data-af="confirm">Apply crop</button>
</div>
`;

export default new ImageEditor();
