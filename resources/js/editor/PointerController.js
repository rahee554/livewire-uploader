/**
 * Pan, pinch-zoom, wheel-zoom and corner-handle resizing for the editor
 * canvas, on unified Pointer Events.
 *
 * This is the orphaned TransformEngine from the previous build, which was
 * never imported — index.js reimplemented the same behaviour twice over with
 * separate mouse and touch paths, re-bound them on every navigation event and
 * never removed the old listeners (AUDIT.md, HIGH-01). Everything here is
 * registered once and released by destroy().
 */
export class PointerController {
    constructor(engine) {
        this.engine = engine;
        this.pointers = new Map();
        this.pinchDistance = 0;
        this.pinchScale = 1;
        this.activeHandle = null;
        this.disposers = [];

        this.attach();
    }

    attach() {
        const canvas = this.engine.canvas;

        this.on(canvas, "pointerdown", (e) => this.onDown(e));
        this.on(canvas, "pointermove", (e) => this.onHover(e));
        this.on(window, "pointermove", (e) => this.onMove(e));
        this.on(window, "pointerup", (e) => this.onUp(e));
        this.on(window, "pointercancel", (e) => this.onUp(e));
        this.on(canvas, "wheel", (e) => this.onWheel(e), { passive: false });

        // Stop the browser treating a drag on the canvas as a page scroll.
        canvas.style.touchAction = "none";
        canvas.style.cursor = "grab";
    }

    on(target, type, handler, options) {
        target.addEventListener(type, handler, options);
        this.disposers.push(() => target.removeEventListener(type, handler, options));
    }

    destroy() {
        this.disposers.forEach((dispose) => dispose());
        this.disposers = [];
        this.pointers.clear();
    }

    onDown(e) {
        this.engine.canvas.setPointerCapture?.(e.pointerId);
        this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (this.engine.state.aspectRatio === 0 && this.pointers.size === 1) {
            const handle = this.handleAt(e);
            if (handle) {
                this.activeHandle = handle;
                return;
            }
        }

        if (this.pointers.size === 2) {
            this.pinchDistance = this.distance();
            this.pinchScale = this.engine.state.scale;
        }

        this.engine.canvas.style.cursor = "grabbing";
    }

    /** Cursor feedback only — runs whether or not a pointer is down. */
    onHover(e) {
        if (this.pointers.size > 0 || this.engine.state.aspectRatio !== 0) return;

        const handle = this.handleAt(e);

        this.engine.canvas.style.cursor = handle === "tl" || handle === "br"
            ? "nwse-resize"
            : handle === "tr" || handle === "bl"
                ? "nesw-resize"
                : "grab";
    }

    onMove(e) {
        const previous = this.pointers.get(e.pointerId);
        if (!previous) return;

        const dx = e.clientX - previous.x;
        const dy = e.clientY - previous.y;
        this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (this.activeHandle) {
            this.resizeMask(this.activeHandle, dx, dy);
            this.engine.render();
            return;
        }

        if (this.pointers.size >= 2) {
            this.pinch();
            return;
        }

        this.engine.state.centerX += dx;
        this.engine.state.centerY += dy;
        this.engine.render();
    }

    onUp(e) {
        this.engine.canvas.releasePointerCapture?.(e.pointerId);
        this.pointers.delete(e.pointerId);

        if (this.pointers.size < 2) this.pinchDistance = 0;
        if (this.pointers.size === 0) {
            this.activeHandle = null;
            this.engine.canvas.style.cursor = "grab";
        }
    }

    onWheel(e) {
        e.preventDefault();

        const { x, y } = this.localPoint(e);
        this.engine.zoom(e.deltaY > 0 ? 0.92 : 1.08, x, y);
    }

    pinch() {
        if (!this.pinchDistance) return;

        const points = [...this.pointers.values()];
        const factor = this.distance() / this.pinchDistance;
        const rect = this.engine.canvas.getBoundingClientRect();

        const midX = (points[0].x + points[1].x) / 2 - rect.left;
        const midY = (points[0].y + points[1].y) / 2 - rect.top;

        // zoom() is relative, so divide out the scale already applied.
        const delta = (this.pinchScale * factor) / this.engine.state.scale;

        if (Number.isFinite(delta) && delta > 0) {
            this.engine.zoom(delta, midX, midY);
        }
    }

    distance() {
        const points = [...this.pointers.values()];
        if (points.length < 2) return 0;

        return Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
    }

    /**
     * offsetX/offsetY are relative to whatever the event hit, which is not the
     * canvas once pointer capture is active, so derive the point ourselves.
     */
    localPoint(e) {
        const rect = this.engine.canvas.getBoundingClientRect();

        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    }

    handleAt(e) {
        const { x, y } = this.localPoint(e);
        const hit = 22;

        const names = ["tl", "tr", "bl", "br"];
        const corners = this.engine.getHandles();

        for (let i = 0; i < corners.length; i++) {
            const [cx, cy] = corners[i];
            if (Math.hypot(x - cx, y - cy) < hit) return names[i];
        }

        return null;
    }

    /** Corner drags resize symmetrically about the centre, so the mask stays centred. */
    resizeMask(handle, dx, dy) {
        const state = this.engine.state;
        const minimum = 48;

        const widthSign = handle === "tl" || handle === "bl" ? -1 : 1;
        const heightSign = handle === "tl" || handle === "tr" ? -1 : 1;

        state.maskW = Math.max(minimum, Math.min(state.maskW + widthSign * dx * 2, this.engine.width - 20));
        state.maskH = Math.max(minimum, Math.min(state.maskH + heightSign * dy * 2, this.engine.height - 20));
    }
}
