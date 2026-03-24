import { CanvasEngine } from "./CanvasEngine.js";
import { ExportEngine } from "./ExportEngine.js";
import { LivewireAdapter } from "./LivewireAdapter.js";

class CropperApp {
    constructor() {
        try {
            this.engine = new CanvasEngine("af-canvas");
            this.exporter = new ExportEngine();
            this.activeInput = null;
            this.modal = document.getElementById("af-modal");
            this.isModalOpen = false;
            this.initialized = false;

            // CRITICAL: Move the modal to <body> so it is never trapped inside a
            // display:none ancestor (e.g. a tab container with x-show="false").
            // position:fixed only escapes overflow, not a display:none parent.
            if (this.modal && this.modal.parentElement !== document.body) {
                document.body.appendChild(this.modal);
            }
            
            // Initialize bridge
            this.livewire = new LivewireAdapter(this);
            
            this.initUI();
            this.scan();
            
            // Support wire:navigate - reinitialize on page changes
            this.setupNavigationListeners();
            this.initialized = true;
        } catch (err) {
            console.warn("AF_Cropper initialization warning:", err.message);
        }
    }
    
    setupNavigationListeners() {
        // Livewire 3+ navigate events
        document.addEventListener('livewire:navigated', () => {
            this.reinitializeOnNavigation();
        });
        
        // Also listen for livewire:init in case component loads after initial page
        document.addEventListener('livewire:init', () => {
            this.reinitializeOnNavigation();
        });
        
        // Turbo/Turbolinks support
        document.addEventListener('turbo:load', () => {
            this.reinitializeOnNavigation();
        });
    }
    
    reinitializeOnNavigation() {
        // Re-acquire modal reference (DOM may have been replaced)
        this.modal = document.getElementById("af-modal");
        
        // Re-initialize UI bindings if modal exists
        if (this.modal) {
            this.initUI();
        }
        
        // Scan for new inputs
        this.scan();
    }

    initUI() {
        // Core Buttons
        const cancelBtn = document.getElementById("af-cancel");
        if (cancelBtn) cancelBtn.onclick = () => this.closeModal(true);
        
        const confirmBtn = document.getElementById("af-confirm");
        if (confirmBtn) confirmBtn.onclick = () => this.confirmCrop();

        // Ratio Selectors
        document.querySelectorAll("[data-ratio]").forEach(btn => {
            btn.onclick = () => {
                let r = btn.dataset.ratio;
                if (r.includes("/")) {
                    const [w, h] = r.split("/").map(Number);
                    r = w / h;
                } else {
                    r = parseFloat(r);
                }
                
                // If we are currently in a flipped state, keep the relative orientation if vertical
                const currentRatio = this.engine.state.aspectRatio;
                if (currentRatio > 0 && currentRatio < 1 && r > 1) {
                    r = 1 / r;
                }

                this.engine.setAspectRatio(r);
                document.querySelectorAll("[data-ratio]").forEach(b => b.classList.remove("active"));
                btn.classList.add("active");

                // Toggle Circle button visibility if ratio is 1
                const circleBtn = document.getElementById("af-ratio-circle");
                if (circleBtn) {
                    const isSquare = Math.abs(r - 1) < 0.01;
                    circleBtn.classList.toggle("visible", isSquare);
                    circleBtn.classList.remove("active");
                }

                // Toggle Free controls
                const freeControls = document.getElementById("af-free-controls");
                if (freeControls) {
                    freeControls.style.display = r === 0 ? "flex" : "none";
                }
            };
        });

        // Freeform controls (if present)
        const fwi = document.getElementById("af-free-w-inc");
        if (fwi) fwi.onclick = () => this.engine.resizeMask(20, 0);
        const fwd = document.getElementById("af-free-w-dec");
        if (fwd) fwd.onclick = () => this.engine.resizeMask(-20, 0);
        const fhi = document.getElementById("af-free-h-inc");
        if (fhi) fhi.onclick = () => this.engine.resizeMask(0, 20);
        const fhd = document.getElementById("af-free-h-dec");
        if (fhd) fhd.onclick = () => this.engine.resizeMask(0, -20);

        // Orientation Flip (if present)
        const flipBtn = document.getElementById("af-ratio-flip");
        if (flipBtn) {
            flipBtn.onclick = () => {
                const current = this.engine.state.aspectRatio;
                if (current !== 0 && current !== 1) {
                    this.engine.setAspectRatio(1 / current);
                }
            };
        }

        const circleBtn = document.getElementById("af-ratio-circle");
        if (circleBtn) {
            circleBtn.onclick = () => {
                this.engine.toggleMask();
                circleBtn.classList.toggle("active", this.engine.state.isCircle);
            };
        }

        const fitBtn = document.getElementById("af-fit-view");
        if (fitBtn) {
            fitBtn.onclick = () => this.engine.fitImage();
        }

        // Zoom & Rotate
        const zoomIn = document.getElementById("af-zoom-in");
        if (zoomIn) zoomIn.onclick = () => this.engine.zoom(1.1);
        
        const zoomOut = document.getElementById("af-zoom-out");
        if (zoomOut) zoomOut.onclick = () => this.engine.zoom(0.9);
        
        const rotVal = document.getElementById("af-rot-val");
        const rotLeft = document.getElementById("af-rot-left");
        if (rotLeft) {
            rotLeft.onclick = () => {
                this.engine.rotate(-90);
                if (rotVal) rotVal.textContent = `${this.engine.state.rotation}°`;
            };
        }
        
        const rotRight = document.getElementById("af-rot-right");
        if (rotRight) {
            rotRight.onclick = () => {
                this.engine.rotate(90);
                if (rotVal) rotVal.textContent = `${this.engine.state.rotation}°`;
            };
        }

        // Interaction
        const canvasWrap = document.querySelector(".af-canvas-wrapper");
        if (canvasWrap) {
            canvasWrap.onwheel = (e) => {
                e.preventDefault();
                this.engine.zoom(e.deltaY > 0 ? 0.95 : 1.05, e.offsetX, e.offsetY);
            };

            let isDragging = false, lastX, lastY;
            let activeHandle = null;

            canvasWrap.addEventListener("mousemove", (e) => {
                if (this.isModalOpen && !isDragging && activeHandle === null) {
                    const rect = canvasWrap.getBoundingClientRect();
                    const mx = e.clientX - rect.left;
                    const my = e.clientY - rect.top;

                    if (this.engine.state.aspectRatio === 0) {
                        const handles = this.engine.getHandles();
                        let hover = -1;
                        handles.forEach((h, i) => {
                            if (Math.hypot(h[0] - mx, h[1] - my) < 20) hover = i;
                        });

                        if (hover === 0 || hover === 3) canvasWrap.style.cursor = "nwse-resize";
                        else if (hover === 1 || hover === 2) canvasWrap.style.cursor = "nesw-resize";
                        else canvasWrap.style.cursor = "crosshair";
                    } else {
                        canvasWrap.style.cursor = "move";
                    }
                }
            });

            canvasWrap.onmousedown = (e) => {
                const rect = canvasWrap.getBoundingClientRect();
                const mx = e.clientX - rect.left;
                const my = e.clientY - rect.top;

                lastX = e.clientX;
                lastY = e.clientY;

                // Check for handles in Free mode
                if (this.engine.state.aspectRatio === 0) {
                    const handles = this.engine.getHandles();
                    handles.forEach((h, i) => {
                        if (Math.hypot(h[0] - mx, h[1] - my) < 25) { // Slightly larger hitbox for touch/mouse
                            activeHandle = i;
                        }
                    });
                }

                if (activeHandle === null) {
                    isDragging = true;
                }
            };

            window.addEventListener("mousemove", (e) => {
                if (this.isModalOpen) {
                    const dx = e.clientX - lastX;
                    const dy = e.clientY - lastY;

                    if (activeHandle !== null) {
                        // Resizing
                        const dw = [0, 1, 2, 3].includes(activeHandle) ? dx : 0;
                        const dh = [0, 1, 2, 3].includes(activeHandle) ? dy : 0;
                        
                        // Simple 2x factor because mask is centered
                        this.engine.resizeMask(
                            [0, 2].includes(activeHandle) ? -dx * 2 : dx * 2,
                            [0, 1].includes(activeHandle) ? -dy * 2 : dy * 2
                        );
                    } else if (isDragging) {
                        // Panning
                        this.engine.state.centerX += dx;
                        this.engine.state.centerY += dy;
                        this.engine.render();
                    }
                    lastX = e.clientX;
                    lastY = e.clientY;
                }
            });

            window.addEventListener("mouseup", () => {
                isDragging = false;
                activeHandle = null;
            });

            // Touch support
            let lastTouchDist = 0;
            let lastTouchX, lastTouchY;
            let touchHandle = null;

            canvasWrap.addEventListener("touchstart", (e) => {
                if (e.touches.length === 1) {
                    const rect = canvasWrap.getBoundingClientRect();
                    const mx = e.touches[0].clientX - rect.left;
                    const my = e.touches[0].clientY - rect.top;

                    // Handle detection for touch
                    if (this.engine.state.aspectRatio === 0) {
                        const handles = this.engine.getHandles();
                        handles.forEach((h, i) => {
                            if (Math.hypot(h[0] - mx, h[1] - my) < 35) { // Braod hit area for fingers
                                touchHandle = i;
                            }
                        });
                    }

                    lastTouchX = e.touches[0].clientX;
                    lastTouchY = e.touches[0].clientY;
                } else if (e.touches.length === 2) {
                    lastTouchDist = Math.hypot(
                        e.touches[0].clientX - e.touches[1].clientX,
                        e.touches[0].clientY - e.touches[1].clientY
                    );
                }
            }, { passive: false });

            canvasWrap.addEventListener("touchmove", (e) => {
                e.preventDefault();
                if (e.touches.length === 1) {
                    const dx = e.touches[0].clientX - lastTouchX;
                    const dy = e.touches[0].clientY - lastTouchY;

                    if (touchHandle !== null) {
                        this.engine.resizeMask(
                            [0, 2].includes(touchHandle) ? -dx * 2 : dx * 2,
                            [0, 1].includes(touchHandle) ? -dy * 2 : dy * 2
                        );
                    } else {
                        this.engine.state.centerX += dx;
                        this.engine.state.centerY += dy;
                        this.engine.render();
                    }

                    lastTouchX = e.touches[0].clientX;
                    lastTouchY = e.touches[0].clientY;
                } else if (e.touches.length === 2) {
                    const dist = Math.hypot(
                        e.touches[0].clientX - e.touches[1].clientX,
                        e.touches[0].clientY - e.touches[1].clientY
                    );
                    if (lastTouchDist > 0) {
                        const delta = dist / lastTouchDist;
                        this.engine.zoom(delta);
                    }
                    lastTouchDist = dist;
                }
            }, { passive: false });

            canvasWrap.addEventListener("touchend", () => {
                touchHandle = null;
                lastTouchDist = 0;
            });
        }
    }

    scan() {
        // Find all cropper inputs that need initialization
        document.querySelectorAll("input[af-cropper='true']:not(.af-initialized)").forEach(input => {
            this.setupDropzone(input);
            input.classList.add("af-initialized");
        });
        
        // Also re-check already initialized inputs in case DOM was replaced
        document.querySelectorAll("input[af-cropper='true'].af-initialized").forEach(input => {
            // Verify the dropzone still exists
            const dz = input.closest(".af-dropzone");
            if (!dz || !input._af_cleanup) {
                // Re-initialize if bindings are lost
                input.classList.remove("af-initialized");
                this.setupDropzone(input);
                input.classList.add("af-initialized");
            }
        });
    }

    setupDropzone(input) {
        // Clear previous initialization if any
        if (input._af_cleanup) input._af_cleanup();
        
        const dz = input.closest(".af-dropzone");
        if (!dz) return;

        // Force IDs to be synced if they somehow differ
        const uniqueId = input.dataset.afId || input.id;
        if (!uniqueId) {
            console.error("AF Uploader: Input missing ID for isolation.");
            return;
        }

        // Sync variant styles from dataset if not already applied by CSS classes
        if (input.dataset.afIsCircle === "true") {
            dz.classList.add("af-dz-circled");
        }

        const triggerClick = (e) => {
            if (e.target.closest(".af-clear-btn") || e.target.closest(".af-download-btn")) return;
            if (e._afHandled) return;
            e._afHandled = true;
            input.click();
        };

        const onFileChange = (e) => {
            e.stopPropagation();
            this.handleFile(e, input);
        };

        const onDragOver = (e) => { 
            e.preventDefault(); 
            e.stopPropagation();
            dz.classList.add("drag-active"); 
        };

        const onDragLeave = (e) => {
            e.preventDefault();
            e.stopPropagation();
            dz.classList.remove("drag-active");
        };

        const onDrop = (e) => {
            e.preventDefault();
            e.stopPropagation();
            dz.classList.remove("drag-active");
            if (e.dataTransfer.files.length) {
                const container = new DataTransfer();
                container.items.add(e.dataTransfer.files[0]);
                input.files = container.files;
                this.handleFile({ target: input }, input);
            }
        };

        dz.addEventListener("click", triggerClick);
        input.addEventListener("change", onFileChange);
        dz.addEventListener("dragover", onDragOver);
        dz.addEventListener("dragleave", onDragLeave);
        dz.addEventListener("drop", onDrop);

        input._af_initialized = true;
        input._af_cleanup = () => {
            dz.removeEventListener("click", triggerClick);
            input.removeEventListener("change", onFileChange);
            dz.removeEventListener("dragover", onDragOver);
            dz.removeEventListener("dragleave", onDragLeave);
            dz.removeEventListener("drop", onDrop);
            delete input._af_initialized;
            delete input._af_cleanup;
        };
    }

    parsePhysicalUnit(val) {
        if (!val) return null;
        const num = parseFloat(val);
        const unit = val.replace(/[0-9.]/g, "").toLowerCase();
        switch(unit) {
            case "cm": return num * 10;
            case "in": return num * 25.4;
            case "px": return num * 0.2645;
            default: return num; // default mm
        }
    }

    handleFile(e, input) {
        const files = Array.from(e.target.files);
        if (!files.length) return;

        const ds = input.dataset;
        const isCropper = input.getAttribute("af-cropper") === "true";
        const isMultiple = input.hasAttribute("multiple");
        
        // CRITICAL: Always use data-af-id which is passed from Blade component
        const uniqueId = ds.afId || input.id || null;
        
        this.activeInput = input;

        // If it's a single image and cropper is enabled, open modal
        if (isCropper && !isMultiple && files[0].type.startsWith("image/")) {
            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = () => this.openModal(img);
                img.src = event.target.result;
            };
            reader.readAsDataURL(files[0]);
        } else {
            // Dispatch event with unique ID for instance isolation (mainly for Drag & Drop support)
            const eventName = uniqueId ? `af-external-file-selected-${uniqueId}` : 'af-external-file-selected';
            window.dispatchEvent(new CustomEvent(eventName, { 
                detail: { 
                    input, 
                    files: files, 
                    file: files[0], 
                    isCropped: false,
                    id: uniqueId
                }
            }));
        }
    }

    openModal(img) {
        if (!this.modal) return;

        // Ensure the modal is always a direct child of <body>.
        // If it ended up inside a display:none tab container (common with x-show),
        // position:fixed still renders relative to the viewport BUT the element
        // itself inherits display:none from the parent and is never painted.
        if (this.modal.parentElement !== document.body) {
            document.body.appendChild(this.modal);
        }
        
        this.modal.classList.add("active");
        this.isModalOpen = true;

        const ds = this.activeInput.dataset;
        let ratio = 1;

        // Check physical units first
        const pW = this.parsePhysicalUnit(ds.afWidth);
        const pH = this.parsePhysicalUnit(ds.afHeight);
        if (pW && pH) {
            ratio = pW / pH;
        } else if (ds.afRatio) {
            if (ds.afRatio.toString().includes("/")) {
                const parts = ds.afRatio.split("/");
                ratio = parseFloat(parts[0]) / parseFloat(parts[1]);
            } else {
                ratio = parseFloat(ds.afRatio);
            }
        }

        this.engine.setAspectRatio(ratio);

        const circleBtn = document.getElementById("af-ratio-circle");
        if (circleBtn) {
            const isSquare = Math.abs(ratio - 1) < 0.01;
            circleBtn.classList.toggle("visible", isSquare);
            if (isSquare && ds.afIsCircle === "true") {
                this.engine.state.isCircle = true;
                circleBtn.classList.add("active");
            } else {
                this.engine.state.isCircle = false;
                circleBtn.classList.remove("active");
            }
        }

        const freeControls = document.getElementById("af-free-controls");
        if (freeControls) {
            freeControls.style.display = ratio === 0 ? "flex" : "none";
        }

        const isLocked = ds.afLockAspect === "true" || (pW && pH);
        const ratioGroup = document.querySelector(".af-ratio-group");
        if (ratioGroup) ratioGroup.style.display = isLocked ? "none" : "flex";
        
        const rotVal = document.getElementById("af-rot-val");
        if (rotVal) rotVal.textContent = "0°";

        // Set the image AFTER the browser has painted the now-visible modal so
        // the canvas wrapper has real pixel dimensions. Double rAF guarantees
        // at least one full layout + paint cycle has completed.
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                this.engine.setImage(img);
            });
        });
    }

    closeModal(isCancel = false) {
        this.modal.classList.add("closing");
        setTimeout(() => {
            this.modal.classList.remove("active", "closing");
            this.isModalOpen = false;
            if (isCancel && this.activeInput) {
                this.activeInput.value = "";
            }
        }, 400);
    }

    showStatus(input, text, type = "success") {
        const uniqueId = input.dataset.afId || input.id || null;
        const eventName = uniqueId ? `af-status-update-${uniqueId}` : 'af-status-update';
        
        window.dispatchEvent(new CustomEvent(eventName, { 
            detail: { text, type, input, id: uniqueId } 
        }));
    }

    clear(input) {
        input.value = "";
    }

    setProgress(input, percent) {
        // Handled via Livewire/Alpine progress
    }

    async confirmCrop() {
        const btn = document.getElementById("af-confirm");
        const originalText = btn ? btn.textContent : "Confirm";
        
        try {
            if (btn) {
                btn.textContent = "Processing...";
                btn.disabled = true;
            }

            const area = this.engine.getCropArea();
            const ds = this.activeInput.dataset;
            const uniqueId = ds.afId || this.activeInput.id || null;
            
            const convertFormat = ds.afConvert || 'webp';
            const isLossless = ds.afLossless === 'true';
            // Quality: lossless overrides all; explicit quality prop wins; default 0.80 for webp converts
            const quality = isLossless ? 1.0 : parseFloat(ds.afQuality || 0.80);
            
            const options = {
                quality,
                format: 'image/webp',
                maxWidth: parseInt(ds.afMaxWidth || 2000),
                maxSizeKB: parseInt(ds.afMaxSize || 0)
            };

            const blob = await this.exporter.export(this.engine, area, options);
            if (!blob) throw new Error("Export failed");

            const file = new File([blob], `cropped_${Date.now()}.webp`, { type: 'image/webp' });
            
            const container = new DataTransfer();
            container.items.add(file);
            this.activeInput.files = container.files;

            // Dispatch with unique ID for instance isolation
            const eventName = uniqueId ? `af-image-cropped-${uniqueId}` : 'af-image-cropped';
            window.dispatchEvent(new CustomEvent(eventName, { 
                detail: { input: this.activeInput, blob, file, id: uniqueId }
            }));

            this.closeModal(false);
        } catch (err) {
            console.error("Cropper Export Error:", err);
            alert("Failed to process image. Please try again.");
        } finally {
            if (btn) {
                btn.textContent = originalText;
                btn.disabled = false;
            }
        }
    }
}

window.AF_Cropper = new CropperApp();

