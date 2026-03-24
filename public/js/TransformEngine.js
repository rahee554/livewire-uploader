export class TransformEngine {
    constructor(canvasEngine) {
        this.engine = canvasEngine;
        this.activePointers = new Map();
        this.initialDistance = 0;
        this.initialScale = 1;
        this.isDraggingMask = false;
        this.activeHandle = null;

        this.init();
    }

    init() {
        const canvas = this.engine.canvas;
        canvas.addEventListener("pointerdown", e => this.onPointerDown(e));
        window.addEventListener("pointermove", e => this.onPointerMove(e));
        window.addEventListener("pointerup", e => this.onPointerUp(e));
        window.addEventListener("pointercancel", e => this.onPointerUp(e));
        canvas.addEventListener("wheel", e => this.onWheel(e), { passive: false });
    }

    onPointerDown(e) {
        this.activePointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (this.engine.state.aspectRatio === 0) {
            const handle = this.getHandleAt(e.offsetX, e.offsetY);
            if (handle) {
                this.isDraggingMask = true;
                this.activeHandle = handle;
                this.engine.canvas.setPointerCapture(e.pointerId);
                return;
            }
        }

        if (this.activePointers.size === 2) {
            this.initialDistance = this.getDistance();
            this.initialScale = this.engine.state.scale;
        }
    }

    onPointerMove(e) {
        // Update cursors for handles
        if (this.engine.state.aspectRatio === 0 && this.activePointers.size <= 1) {
            const handle = this.getHandleAt(e.offsetX, e.offsetY);
            this.updateCursor(handle);
        }

        if (!this.activePointers.has(e.pointerId)) return;
        
        const prev = this.activePointers.get(e.pointerId);
        const dx = (e.clientX - prev.x);
        const dy = (e.clientY - prev.y);
        this.activePointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (this.isDraggingMask && this.activeHandle) {
            this.resizeMask(this.activeHandle, dx, dy);
            this.engine.render();
            return;
        }

        if (this.activePointers.size === 2) {
            const currentDist = this.getDistance();
            const factor = currentDist / this.initialDistance;
            
            // Zoom from pinch center
            const pts = Array.from(this.activePointers.values());
            const meanX = (pts[0].x + pts[1].x) / 2;
            const meanY = (pts[0].y + pts[1].y) / 2;
            const rect = this.engine.canvas.getBoundingClientRect();
            
            const delta = factor / (this.engine.state.scale / this.initialScale);
            this.engine.zoom(delta, meanX - rect.left, meanY - rect.top);
        } else if (this.activePointers.size === 1) {
            this.engine.state.centerX += dx;
            this.engine.state.centerY += dy;
            this.engine.render();
        }
    }

    onPointerUp(e) {
        this.activePointers.delete(e.pointerId);
        if (this.activePointers.size < 2) {
            this.initialDistance = 0;
        }
        this.isDraggingMask = false;
        this.activeHandle = null;
        if (!this.isDraggingMask) {
            this.engine.canvas.style.cursor = "grab";
        }
    }

    getDistance() {
        const pts = Array.from(this.activePointers.values());
        if (pts.length < 2) return 0;
        return Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
    }

    onWheel(e) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? 0.9 : 1.1;
        this.engine.zoom(delta, e.offsetX, e.offsetY);
    }

    getHandleAt(x, y) {
        const { maskW, maskH } = this.engine.state;
        const mx = (this.engine.width - maskW) / 2;
        const my = (this.engine.height - maskH) / 2;
        const hit = 20;

        if (Math.hypot(x - mx, y - my) < hit) return "tl";
        if (Math.hypot(x - (mx + maskW), y - my) < hit) return "tr";
        if (Math.hypot(x - mx, y - (my + maskH)) < hit) return "bl";
        if (Math.hypot(x - (mx + maskW), y - (my + maskH)) < hit) return "br";
        
        return null;
    }

    updateCursor(handle) {
        if (handle === "tl" || handle === "br") {
            this.engine.canvas.style.cursor = "nwse-resize";
        } else if (handle === "tr" || handle === "bl") {
            this.engine.canvas.style.cursor = "nesw-resize";
        } else {
            this.engine.canvas.style.cursor = "grab";
        }
    }

    resizeMask(handle, dx, dy) {
        const s = this.engine.state;
        const minSize = 40;

        // Symmetric resize from center for better UX
        if (handle === "tl") {
            s.maskW = Math.max(minSize, s.maskW - dx * 2);
            s.maskH = Math.max(minSize, s.maskH - dy * 2);
        } else if (handle === "tr") {
            s.maskW = Math.max(minSize, s.maskW + dx * 2);
            s.maskH = Math.max(minSize, s.maskH - dy * 2);
        } else if (handle === "bl") {
            s.maskW = Math.max(minSize, s.maskW - dx * 2);
            s.maskH = Math.max(minSize, s.maskH + dy * 2);
        } else if (handle === "br") {
            s.maskW = Math.max(minSize, s.maskW + dx * 2);
            s.maskH = Math.max(minSize, s.maskH + dy * 2);
        }

        s.maskW = Math.min(s.maskW, this.engine.width - 20);
        s.maskH = Math.min(s.maskH, this.engine.height - 20);
    }
}
