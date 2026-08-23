/**
 * Client-side image re-encoding: format conversion, downscaling, and a
 * target-size search.
 *
 * Doing this before the upload means the network only ever carries the final
 * bytes, and the server never handles the 12MP original at all.
 *
 * Two things the old pipeline got wrong are fixed here:
 *
 *  - `convert` was read into a variable and then ignored in favour of a
 *    hardcoded image/webp (AUDIT.md, HIGH-06).
 *  - `target-size` reached the DOM as data-af-target-size but the encoder read
 *    data-af-max-size, an attribute nothing ever wrote (AUDIT.md, HIGH-05).
 */

import { extensionOf } from "./files.js";

const MIME_BY_FORMAT = {
    webp: "image/webp",
    jpeg: "image/jpeg",
    png: "image/png",
};

/** Formats whose encoder ignores the quality argument. */
const LOSSLESS_FORMATS = ["image/png"];

/**
 * Decode a file into an ImageBitmap with EXIF orientation already applied.
 *
 * `imageOrientation: "from-image"` is what stops portrait phone photos being
 * cropped sideways — the old build decoded through an <img> and never looked
 * at the orientation tag.
 */
export async function decode(file) {
    if (typeof createImageBitmap === "function") {
        try {
            return await createImageBitmap(file, { imageOrientation: "from-image" });
        } catch {
            // Safari < 16 and some Android builds reject the options bag.
            try {
                return await createImageBitmap(file);
            } catch {
                /* fall through to the <img> path */
            }
        }
    }

    return await decodeViaImageElement(file);
}

function decodeViaImageElement(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error("The image could not be decoded."));
        };
        image.src = url;
    });
}

/**
 * Re-encode an image according to the resolved options.
 *
 * Returns the original file untouched when there is nothing worth doing, or
 * when the re-encoded result would actually be larger — converting a
 * well-compressed JPEG to WebP at high quality can inflate it.
 *
 * @param {File} file
 * @param {{convert: ?string, quality: number, lossless: boolean,
 *          maxWidth: ?number, maxHeight: ?number, targetSize: ?number}} options
 * @returns {Promise<File>}
 */
/**
 * Resolve the byte budget for an encode.
 *
 * Two ceilings can be in play at once — an absolute one from target-size and a
 * relative one from compress. Whichever is tighter wins, so
 * `target-size="500KB" compress="50%"` on a 600KB file yields 300KB, and on a
 * 4MB file yields 500KB.
 *
 * @returns {?number} bytes, or null when the output is unconstrained.
 */
export function resolveBudget(originalBytes, targetSize = null, compress = null) {
    const ceilings = [];

    if (targetSize > 0) ceilings.push(targetSize);
    if (compress > 0 && originalBytes > 0) ceilings.push(Math.round(originalBytes * (compress / 100)));

    return ceilings.length > 0 ? Math.min(...ceilings) : null;
}

export async function transcode(file, options = {}) {
    const {
        convert = null,
        quality = 0.82,
        lossless = false,
        maxWidth = null,
        maxHeight = null,
    } = options;

    const targetSize = resolveBudget(file.size, options.targetSize, options.compress);

    if (!file.type.startsWith("image/") || file.type === "image/svg+xml") {
        return file;
    }

    const targetType = convert ? MIME_BY_FORMAT[convert] : file.type;

    if (!targetType || !canEncode(targetType)) {
        return file;
    }

    const bitmap = await decode(file);
    const scaled = fitWithin(bitmap.width, bitmap.height, maxWidth, maxHeight);

    const needsResize = scaled.width !== bitmap.width || scaled.height !== bitmap.height;
    const needsConvert = targetType !== file.type;
    const needsShrink = targetSize !== null && file.size > targetSize;

    if (!needsResize && !needsConvert && !needsShrink) {
        close(bitmap);
        return file;
    }

    const effectiveQuality = lossless || LOSSLESS_FORMATS.includes(targetType) ? undefined : quality;

    const result = await encodeWithinBudget({
        width: scaled.width,
        height: scaled.height,
        type: targetType,
        quality: effectiveQuality,
        budget: targetSize,
        draw: (context, width, height) => context.drawImage(bitmap, 0, 0, width, height),
    });

    close(bitmap);

    if (!result?.blob) return file;

    // An explicit convert or resize is honoured even if it grows the file —
    // the caller asked for that format. A pure size-reduction pass that ends
    // up larger is pointless, so keep the original.
    if (result.blob.size >= file.size && !needsResize && !needsConvert) {
        return file;
    }

    const output = new File([result.blob], renameFor(file.name, targetType), {
        type: targetType,
        lastModified: Date.now(),
    });

    // Lets the UI say so when a budget could not be reached, rather than
    // reporting a reduction that quietly missed the target.
    output.budget = targetSize;
    output.budgetMet = result.met;

    return output;
}

/**
 * Encode to fit a byte budget, trading quality first and resolution second.
 *
 * Quality alone cannot always get there. A photo of foliage or noise stays
 * large even at quality 0.1, and the old code simply returned whatever it had
 * and reported the shortfall as a success. Bytes scale roughly with pixel
 * count, so when the quality search bottoms out this drops the dimensions by
 * `sqrt(budget / size)` and tries again — which is what any serious
 * compressor does.
 *
 * @param {{width: number, height: number, type: string, quality: ?number,
 *          budget: ?number, draw: (ctx: CanvasRenderingContext2D, w: number, h: number) => void}} spec
 * @returns {Promise<?{blob: Blob, width: number, height: number, met: boolean}>}
 */
export async function encodeWithinBudget({ width, height, type, quality, budget, draw }) {
    const lossless = quality === undefined || LOSSLESS_FORMATS.includes(type);

    let currentWidth = width;
    let currentHeight = height;
    let smallest = null;

    // Four rounds takes a 4000px image down past 1000px; beyond that the
    // budget is unreachable and the caller is better off being told.
    for (let round = 0; round < 5; round++) {
        const canvas = createCanvas(currentWidth, currentHeight);
        const context = canvas.getContext("2d", { alpha: type !== "image/jpeg" });

        if (!context) break;

        // JPEG has no alpha; without this, transparent pixels come out black.
        if (type === "image/jpeg") {
            context.fillStyle = "#ffffff";
            context.fillRect(0, 0, currentWidth, currentHeight);
        }

        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = "high";
        draw(context, currentWidth, currentHeight);

        const encode = (q) => toBlob(canvas, type, q);

        let blob = await encode(lossless ? undefined : quality);
        if (!blob) break;

        const fits = () => ({ blob, width: currentWidth, height: currentHeight, met: true });

        if (!budget || blob.size <= budget) return fits();

        if (!lossless) {
            const searched = await searchQuality(encode, budget, quality, blob);

            if (searched) {
                blob = searched;
                if (blob.size <= budget) return fits();
            }
        }

        if (!smallest || blob.size < smallest.blob.size) {
            smallest = { blob, width: currentWidth, height: currentHeight, met: false };
        }

        // Estimate the scale that would land on the budget, and clamp it so a
        // wild estimate cannot collapse the image in one step.
        const estimate = Math.sqrt(budget / blob.size);
        const factor = Math.max(0.55, Math.min(0.85, Number.isFinite(estimate) ? estimate : 0.75));

        const nextWidth = Math.round(currentWidth * factor);
        const nextHeight = Math.round(currentHeight * factor);

        if (nextWidth < 96 || nextHeight < 96) break;

        currentWidth = nextWidth;
        currentHeight = nextHeight;
    }

    return smallest;
}

/**
 * Binary-search the quality axis for the largest value that still fits under
 * the byte budget.
 *
 * The previous implementation returned whichever blob the last iteration
 * happened to produce, which could be one that overshot the target. This keeps
 * the best under-budget result it has seen and only falls back to the smallest
 * attempt when nothing fits.
 */
async function searchQuality(encode, targetSize, startQuality) {
    let low = 0.08;
    let high = startQuality;
    let best = null;
    let smallest = null;

    for (let i = 0; i < 7; i++) {
        const quality = (low + high) / 2;
        const blob = await encode(quality);

        if (!blob) break;

        if (!smallest || blob.size < smallest.size) {
            smallest = blob;
        }

        if (blob.size <= targetSize) {
            best = blob;
            low = quality;

            // Close enough that further passes are not worth the CPU.
            if (blob.size >= targetSize * 0.92) break;
        } else {
            high = quality;
        }
    }

    return best || smallest;
}

function fitWithin(width, height, maxWidth, maxHeight) {
    let scale = 1;

    if (maxWidth && width > maxWidth) scale = Math.min(scale, maxWidth / width);
    if (maxHeight && height > maxHeight) scale = Math.min(scale, maxHeight / height);

    return {
        width: Math.max(1, Math.round(width * scale)),
        height: Math.max(1, Math.round(height * scale)),
    };
}

export function createCanvas(width, height) {
    if (typeof OffscreenCanvas !== "undefined") {
        return new OffscreenCanvas(width, height);
    }

    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = height;

    return canvas;
}

export function toBlob(canvas, type, quality) {
    if (typeof OffscreenCanvas !== "undefined" && canvas instanceof OffscreenCanvas) {
        return canvas.convertToBlob({ type, quality });
    }

    return new Promise((resolve) => canvas.toBlob(resolve, type, quality));
}

/** Feature-detect encoder support so an unsupported convert target degrades. */
function canEncode(type) {
    if (type === "image/jpeg" || type === "image/png") return true;

    const probe = document.createElement("canvas");
    probe.width = 1;
    probe.height = 1;

    return probe.toDataURL(type).startsWith(`data:${type}`);
}

function renameFor(name, type) {
    const extension = Object.entries(MIME_BY_FORMAT).find(([, mime]) => mime === type)?.[0];
    if (!extension) return name;

    const current = extensionOf(name);
    const base = current ? name.slice(0, -(current.length + 1)) : name;

    return `${base || "image"}.${extension}`;
}

function close(bitmap) {
    if (typeof ImageBitmap !== "undefined" && bitmap instanceof ImageBitmap) {
        bitmap.close();
    }
}
