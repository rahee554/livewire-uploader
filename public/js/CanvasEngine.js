export class CanvasEngine {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext("2d", { alpha: true });
        this.dpr = window.devicePixelRatio || 1;
        this.image = null;
        this._initialized = false;
        
        this.state = {
            centerX: 0, 
            centerY: 0, 
            scale: 1,
            rotation: 0,
            isCircle: false,
            aspectRatio: 1,
            maskW: 0,
            maskH: 0
        };

        this.onRender = null;
        this.setupResizeObserver();
    }

    setupResizeObserver() {
        let resizeTimer;
        const observer = new ResizeObserver(() => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                this.resize();
            }, 50); 
        });
        if (this.canvas.parentElement) {
            observer.observe(this.canvas.parentElement);
        }
    }

    resize() {
        const wrapper = this.canvas.parentElement;
        if (!wrapper) return;
        const rect = wrapper.getBoundingClientRect();
        
        // Use floors to prevent sub-pixel issues that trigger ResizeObserver loops
        const newW = Math.floor(rect.width);
        const newH = Math.floor(rect.height);

        if (this.width === newW && this.height === newH) return;

        this.width = newW;
        this.height = newH;

        this.canvas.width = this.width * this.dpr;
        this.canvas.height = this.height * this.dpr;
        this.canvas.style.width = this.width + "px";
        this.canvas.style.height = this.height + "px";

        this.ctx.resetTransform();
        this.ctx.scale(this.dpr, this.dpr);

        if (this.image) {
            if (!this._initialized && this.width > 0) {
                this.reset();
                this._initialized = true;
            } else {
                // If container size changed, we should adapt the mask size but keep it centered
                const newSize = this.getInitialMaskSize();
                this.state.maskW = newSize.width;
                this.state.maskH = newSize.height;
                this.render();
            }
        }
    }

    setImage(image) {
        this.image = image;
        this._initialized = false;
        this.state.rotation = 0;
        
        if (this.width > 0) {
            this.reset();
            this._initialized = true;
        }
    }

    reset() {
        if (!this.image || this.width === 0 || this.height === 0) return;

        const size = this.getInitialMaskSize();
        this.state.maskW = size.width;
        this.state.maskH = size.height;

        const scaleX = this.state.maskW / this.image.width;
        const scaleY = this.state.maskH / this.image.height;
        this.state.scale = Math.max(scaleX, scaleY);
        
        this.state.centerX = this.width / 2;
        this.state.centerY = this.height / 2;

        this.render();
    }

    fitImage() {
        if (!this.image) return;
        const scaleX = this.state.maskW / this.image.width;
        const scaleY = this.state.maskH / this.image.height;
        this.state.scale = Math.min(scaleX, scaleY);
        this.state.centerX = this.width / 2;
        this.state.centerY = this.height / 2;
        this.render();
    }

    zoom(delta, pivotX = this.width / 2, pivotY = this.height / 2) {
        this.state.scale *= delta;
        this.state.centerX = pivotX - (pivotX - this.state.centerX) * delta;
        this.state.centerY = pivotY - (pivotY - this.state.centerY) * delta;
        this.render();
    }

    render() {
        if (!this.ctx || !this.width || !this.height) return;

        this.ctx.save();
        this.ctx.setTransform(1, 0, 0, 1, 0, 0);
        this.ctx.fillStyle = "#000000";
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.restore();

        if (this.image) {
            this.ctx.save();
            this.ctx.translate(this.state.centerX, this.state.centerY);
            this.ctx.rotate((this.state.rotation * Math.PI) / 180);
            this.ctx.scale(this.state.scale, this.state.scale);
            this.ctx.drawImage(this.image, -this.image.width / 2, -this.image.height / 2);
            this.ctx.restore();
        }

        this.drawMask();
        if (this.onRender) this.onRender();
    }

    getInitialMaskSize() {
        // Smaller padding on mobile to maximize view
        const padding = this.width < 500 ? 30 : 60;
        const maxW = this.width - padding;
        const maxH = this.height - padding;
        
        if (this.state.aspectRatio === 0) {
            // Free mode should fill the space but keep some padding
            const size = Math.min(maxW, maxH);
            return { width: size, height: size };
        }

        // Maintain aspect ratio while fitting within max bounds
        let w = maxW;
        let h = w / this.state.aspectRatio;

        if (h > maxH) {
            h = maxH;
            w = h * this.state.aspectRatio;
        }

        return { width: w, height: h };
    }

    setAspectRatio(ratio) {
        this.state.aspectRatio = ratio;
        if (ratio !== 1) this.state.isCircle = false;
        
        if (this.image) {
            this.reset(); // Re-center and re-scale to fit new ratio
        }
    }

    drawMask() {
        const maskX = (this.width - this.state.maskW) / 2;
        const maskY = (this.height - this.state.maskH) / 2;

        this.ctx.fillStyle = "rgba(0, 0, 0, 0.75)";
        const path = new Path2D();
        path.rect(0, 0, this.width, this.height);
        
        if (this.state.isCircle && this.state.aspectRatio === 1) {
            path.arc(this.width / 2, this.height / 2, this.state.maskW / 2, 0, Math.PI * 2);
        } else {
            path.rect(maskX, maskY, this.state.maskW, this.state.maskH);
        }
        
        this.ctx.fill(path, "evenodd");

        this.ctx.strokeStyle = "rgba(255, 255, 255, 0.3)";
        this.ctx.lineWidth = 1;
        if (this.state.isCircle && this.state.aspectRatio === 1) {
            this.ctx.beginPath();
            this.ctx.arc(this.width / 2, this.height / 2, this.state.maskW / 2, 0, Math.PI * 2);
            this.ctx.stroke();
        } else {
            this.ctx.strokeRect(maskX, maskY, this.state.maskW, this.state.maskH);
            if (this.state.aspectRatio === 0) {
                this.drawHandles(maskX, maskY, this.state.maskW, this.state.maskH);
            }
        }
    }

    drawHandles(x, y, w, h) {
        this.ctx.fillStyle = "#fff";
        this.ctx.strokeStyle = "#000";
        this.ctx.lineWidth = 1;
        const corners = this.getHandles();
        corners.forEach(([cx, cy], i) => {
            // Adjust handles to be partially inside the lines for a cleaner look
            let ox = (i === 0 || i === 2) ? 2 : -2;
            let oy = (i === 0 || i === 1) ? 2 : -2;
            
            this.ctx.beginPath();
            this.ctx.rect(cx + ox - 4, cy + oy - 4, 8, 8);
            this.ctx.fill();
            this.ctx.stroke();
        });
    }

    getHandles() {
        const x = (this.width - this.state.maskW) / 2;
        const y = (this.height - this.state.maskH) / 2;
        const w = this.state.maskW;
        const h = this.state.maskH;
        return [
            [x, y], [x + w, y], [x, y + h], [x + w, y + h]
        ];
    }

    getCropArea() {
        return {
            x: (this.width - this.state.maskW) / 2,
            y: (this.height - this.state.maskH) / 2,
            width: this.state.maskW,
            height: this.state.maskH
        };
    }

    toggleMask() {
        if (this.state.aspectRatio === 1) {
            this.state.isCircle = !this.state.isCircle;
            this.render();
        }
    }

    resizeMask(deltaW, deltaH) {
        if (this.state.aspectRatio !== 0) return;
        const minSize = 40;
        this.state.maskW = Math.max(minSize, this.state.maskW + deltaW);
        this.state.maskH = Math.max(minSize, this.state.maskH + deltaH);
        this.render();
    }

    rotate(deg) {
        this.state.rotation = (this.state.rotation + deg) % 360;
        this.render();
    }
}

