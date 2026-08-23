/**
 * File classification and accept-matching.
 *
 * The old build carried two different isImageFile() implementations in one
 * object literal, so the more capable of the two was silently dropped at parse
 * time (AUDIT.md, CRIT-04). There is exactly one of each here.
 */

const EXTENSION_KINDS = {
    image: ["jpg", "jpeg", "png", "gif", "webp", "avif", "bmp", "tif", "tiff", "heic", "heif", "svg"],
    video: ["mp4", "webm", "mov", "m4v", "avi", "mkv", "flv", "wmv", "mpg", "mpeg"],
    audio: ["mp3", "wav", "ogg", "oga", "m4a", "flac", "aac", "opus"],
    pdf: ["pdf"],
    doc: ["doc", "docx", "odt", "rtf", "txt", "md", "pages"],
    sheet: ["xls", "xlsx", "csv", "ods", "numbers"],
    slide: ["ppt", "pptx", "odp", "key"],
    archive: ["zip", "rar", "7z", "tar", "gz", "bz2", "xz"],
};

const MIME_KINDS = {
    "image/": "image",
    "video/": "video",
    "audio/": "audio",
    "application/pdf": "pdf",
};

export function extensionOf(name) {
    if (!name) return "";
    const clean = String(name).split(/[?#]/)[0];
    const dot = clean.lastIndexOf(".");
    return dot > 0 ? clean.slice(dot + 1).toLowerCase() : "";
}

/**
 * Best-effort category for an icon. Trusts the MIME type first because a
 * download URL often has no extension at all.
 */
export function fileKind(nameOrFile, mimeType = "") {
    const name = typeof nameOrFile === "string" ? nameOrFile : nameOrFile?.name || "";
    const mime = (mimeType || nameOrFile?.type || "").toLowerCase();

    for (const [prefix, kind] of Object.entries(MIME_KINDS)) {
        if (mime.startsWith(prefix)) return kind;
    }

    const extension = extensionOf(name);

    for (const [kind, extensions] of Object.entries(EXTENSION_KINDS)) {
        if (extensions.includes(extension)) return kind;
    }

    return "file";
}

export function isImage(nameOrFile, mimeType = "") {
    return fileKind(nameOrFile, mimeType) === "image";
}

/**
 * True when a URL can be rendered directly into an <img>.
 *
 * Livewire's temporary preview endpoint serves the real bytes but exposes no
 * extension, so it is decided by the component's accept expression instead.
 */
export function isDisplayableImage(url, accept = "") {
    if (!url || typeof url !== "string") return false;
    if (url.startsWith("data:image/") || url.startsWith("blob:")) return true;

    if (url.includes("/livewire/preview-file/")) {
        const lowered = accept.toLowerCase();
        return lowered === "" || lowered.includes("image");
    }

    return isImage(url);
}

/**
 * Match a File against an HTML accept expression.
 *
 * Mirrors the server-side UploadValidator so the two agree; the server remains
 * the enforcement boundary.
 */
export function matchesAccept(file, accept) {
    const expression = String(accept || "").trim();
    if (expression === "" || expression === "*" || expression === "*/*") return true;

    const mime = (file.type || "").toLowerCase();
    const extension = extensionOf(file.name);

    return expression.split(",").some((raw) => {
        const token = raw.trim().toLowerCase();
        if (token === "") return false;
        if (token.startsWith(".")) return extension === token.slice(1);
        if (token.endsWith("/*")) return mime.startsWith(token.slice(0, -1));
        return mime === token;
    });
}

export function formatBytes(bytes) {
    if (!bytes || bytes < 0) return "0 B";

    const units = ["B", "KB", "MB", "GB", "TB"];
    const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / 1024 ** exponent;

    return `${value >= 10 || exponent === 0 ? Math.round(value) : value.toFixed(1)} ${units[exponent]}`;
}

/** Assign a FileList onto an <input type="file"> without triggering a change. */
export function assignFiles(input, files) {
    const transfer = new DataTransfer();
    files.forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
}
