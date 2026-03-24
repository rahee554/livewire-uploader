export class ExportEngine {
    async export(engine, cropArea, options = {}) {
        let { quality = 1.0, format = "image/webp", maxSizeKB = 0, maxWidth = 0, maxHeight = 0 } = options;
        const { image, state } = engine;
        const isCircle = state.isCircle;

        // Calculate actual source pixel dimensions
        let outW = Math.round(cropArea.width / state.scale);
        let outH = Math.round(cropArea.height / state.scale);

        // Apply Hard Max Constraints if set
        if (maxWidth > 0 && outW > maxWidth) {
            const ratio = maxWidth / outW;
            outW = maxWidth;
            outH = Math.round(outH * ratio);
        }
        if (maxHeight > 0 && outH > maxHeight) {
            const ratio = maxHeight / outH;
            outH = maxHeight;
            outW = Math.round(outW * ratio);
        }

        const canvas = typeof OffscreenCanvas !== "undefined" 
            ? new OffscreenCanvas(outW, outH)
            : document.createElement("canvas");
        
        if (!(canvas instanceof OffscreenCanvas)) {
            canvas.width = outW;
            canvas.height = outH;
        }

        const ctx = canvas.getContext("2d");
        if (!ctx) throw new Error("Export failed.");

        // Clear with transparency
        ctx.clearRect(0, 0, outW, outH);

        const drawOnCanvas = (targetCtx, w, h) => {
            targetCtx.save();
            // Scale factor to map screen-crop space to final-output space
            const finalScaleX = w / (cropArea.width / state.scale);
            
            // Pivot mapping: relative to crop area, then scaled to final
            const exportScale = (1 / state.scale) * finalScaleX;
            
            const relCenterX = (state.centerX - cropArea.x) * exportScale;
            const relCenterY = (state.centerY - cropArea.y) * exportScale;

            targetCtx.translate(relCenterX, relCenterY);
            targetCtx.rotate((state.rotation * Math.PI) / 180);
            targetCtx.scale(exportScale * state.scale, exportScale * state.scale); 
            
            targetCtx.drawImage(image, -image.width / 2, -image.height / 2);
            targetCtx.restore();
        };

        drawOnCanvas(ctx, outW, outH);

        let finalCanvas = canvas;
        if (isCircle) {
            const circleCanvas = typeof OffscreenCanvas !== "undefined" 
                ? new OffscreenCanvas(outW, outH)
                : document.createElement("canvas");
            
            if (!(circleCanvas instanceof OffscreenCanvas)) {
                circleCanvas.width = outW;
                circleCanvas.height = outH;
            }

            const cCtx = circleCanvas.getContext("2d");
            cCtx.beginPath();
            cCtx.arc(outW / 2, outH / 2, Math.min(outW, outH) / 2, 0, Math.PI * 2);
            cCtx.clip();
            cCtx.drawImage(canvas, 0, 0);
            finalCanvas = circleCanvas;
        }

        const getBlob = async (q) => {
            return finalCanvas instanceof OffscreenCanvas 
                ? await finalCanvas.convertToBlob({ type: format, quality: q })
                : new Promise(resolve => finalCanvas.toBlob(resolve, format, q));
        };

        let blob = await getBlob(quality);

        // Iterative reduction for max size
        if (maxSizeKB > 0 && blob.size > maxSizeKB * 1024) {
            let low = 0.5, high = quality;
            for (let i = 0; i < 5; i++) {
                quality = (low + high) / 2;
                blob = await getBlob(quality);
                if (blob.size > maxSizeKB * 1024) high = quality;
                else low = quality;
                if (Math.abs(blob.size/1024 - maxSizeKB) < maxSizeKB * 0.05) break;
            }
        }

        return blob;
    }
}

