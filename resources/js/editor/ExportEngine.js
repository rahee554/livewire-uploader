import { encodeWithinBudget } from "../core/transcode.js";

/**
 * Renders the cropped region at source resolution and encodes it.
 *
 * Changes from the previous version:
 *  - the output format is whatever the caller asks for, instead of always
 *    image/webp regardless of the convert option (AUDIT.md, HIGH-06);
 *  - the target-size search keeps the best blob that fits under the budget
 *    rather than the last one it happened to encode;
 *  - JPEG output gets a white matte, so transparency no longer turns black.
 */
export class ExportEngine {
    /**
     * @param {object} engine  CanvasEngine holding the image and transform state.
     * @param {{x:number,y:number,width:number,height:number}} cropArea  In canvas CSS pixels.
     * @param {{format?: string, quality?: number, maxWidth?: ?number,
     *          maxHeight?: ?number, targetSize?: ?number, circle?: boolean}} options
     * @returns {Promise<Blob>}
     */
    async export(engine, cropArea, options = {}) {
        const {
            format = "image/webp",
            quality = 0.82,
            maxWidth = null,
            maxHeight = null,
            targetSize = null,
            circle = false,
        } = options;

        const { image, state } = engine;

        if (!image) throw new Error("Nothing to export.");

        // The crop rectangle is in on-screen pixels; dividing by the display
        // scale converts it back to source pixels.
        let width = Math.max(1, Math.round(cropArea.width / state.scale));
        let height = Math.max(1, Math.round(cropArea.height / state.scale));

        ({ width, height } = constrain(width, height, maxWidth, maxHeight));

        // Shares the trade-quality-then-resolution search with the plain
        // upload path, so a target size means the same thing whether or not
        // the image went through the editor.
        const result = await encodeWithinBudget({
            width,
            height,
            type: format,
            quality: format === "image/png" ? undefined : quality,
            budget: targetSize,
            draw: (context, outputWidth, outputHeight) => {
                if (circle) {
                    context.beginPath();
                    context.arc(
                        outputWidth / 2,
                        outputHeight / 2,
                        Math.min(outputWidth, outputHeight) / 2,
                        0,
                        Math.PI * 2
                    );
                    context.clip();
                }

                this.paint(context, engine, cropArea, outputWidth);
            },
        });

        if (!result?.blob) throw new Error("The image could not be encoded.");

        return result.blob;
    }

    /**
     * Replay the on-screen transform into the output canvas.
     *
     * Everything is expressed relative to the crop rectangle's top-left corner
     * and then multiplied up to output resolution, so rotation and zoom land
     * exactly where the mask showed them.
     */
    paint(context, engine, cropArea, outputWidth) {
        const { image, state } = engine;

        // Screen pixels -> output pixels.
        const outputScale = outputWidth / cropArea.width;

        context.save();
        context.translate(
            (state.centerX - cropArea.x) * outputScale,
            (state.centerY - cropArea.y) * outputScale
        );
        context.rotate((state.rotation * Math.PI) / 180);
        context.scale(state.scale * outputScale, state.scale * outputScale);
        context.drawImage(image, -image.width / 2, -image.height / 2);
        context.restore();
    }

}

function constrain(width, height, maxWidth, maxHeight) {
    let scale = 1;

    if (maxWidth && width > maxWidth) scale = Math.min(scale, maxWidth / width);
    if (maxHeight && height > maxHeight) scale = Math.min(scale, maxHeight / height);

    return {
        width: Math.max(1, Math.round(width * scale)),
        height: Math.max(1, Math.round(height * scale)),
    };
}
