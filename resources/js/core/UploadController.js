import { fileKind, formatBytes, isDisplayableImage, matchesAccept } from "./files.js";
import { resolveBudget, transcode } from "./transcode.js";

/**
 * The editor pulls in the canvas, export and pointer modules. Most uploaders
 * never open it, so it is fetched on first use rather than on page load.
 */
let editorModule = null;

async function loadEditor() {
    editorModule ??= import("../editor/ImageEditor.js").then((module) => module.default);

    return editorModule;
}

/**
 * The Alpine component behind <x-af-uploader>.
 *
 * One object owns the input for its whole lifetime: it is the only thing that
 * listens for change, drag and drop, and the only thing that talks to Livewire.
 * The previous build split those duties between this layer and a separate
 * cropper module that bound native listeners to the *same* elements and
 * communicated over window CustomEvents (AUDIT.md, HIGH-01, HIGH-02).
 *
 * All per-instance state lives on the instance. There is no page-global cache
 * keyed by property name, which is what let two components binding
 * wire:model="photo" overwrite each other's preview (AUDIT.md, CRIT-03).
 */
export function createUploader(options, entangled = null) {
    return {
        // ------------------------------------------------------------ config
        options,
        id: options.id,
        model: options.model,
        accept: options.accept,
        multiple: options.multiple,

        /**
         * Two-way binding to the Livewire property.
         *
         * Declared as a data property rather than read through a closure so
         * $watch can observe it and assignments propagate back to the server.
         */
        entangled,

        // ------------------------------------------------------------- state
        files: [],          // [{ name, size, preview, kind }]
        pending: [],        // File objects waiting for a manual upload
        uploading: false,
        progress: 0,
        message: "",
        messageTone: "",    // info | success | error
        dragging: false,
        busy: false,        // transcoding or cropping

        /**
         * True when the dropzone is too short for the circular progress ring.
         *
         * Measured rather than inferred from the variant, because height="70"
         * on a plain uploader is just as cramped as the inline variant. The
         * 54px ring plus its label needs about 120px of room; below that the
         * UI switches to a bar along the bottom edge.
         */
        compact: false,
        resizeObserver: null,

        /** Object URLs this instance created, revoked on teardown. */
        objectUrls: [],
        messageTimer: null,

        // ------------------------------------------------------------- setup

        init() {
            this.syncFromModel(this.entangled);

            // Livewire is the source of truth: when the property changes
            // server-side (a reset, a validation failure, a programmatic set)
            // the UI follows it.
            this.$watch("entangled", (value) => this.syncFromModel(value));

            // Lets the host page clear an uploader without reaching inside it:
            //   $dispatch('af-uploader:clear') on any descendant element.
            this.$el.addEventListener("af-uploader:clear", () => this.clear());

            this.watchSize();
        },

        /** Alpine calls this when the element leaves the DOM. */
        destroy() {
            this.releaseUrls();
            clearTimeout(this.messageTimer);
            this.resizeObserver?.disconnect();
        },

        watchSize() {
            const dropzone = this.$el.querySelector(".af-dropzone");
            if (!dropzone) return;

            const measure = () => {
                this.compact = dropzone.getBoundingClientRect().height < 120;
            };

            measure();

            if (typeof ResizeObserver === "undefined") return;

            this.resizeObserver = new ResizeObserver(measure);
            this.resizeObserver.observe(dropzone);
        },

        get input() {
            return this.$refs.input;
        },

        get hasFiles() {
            return this.files.length > 0;
        },

        get awaitingUpload() {
            return this.pending.length > 0 && !this.uploading;
        },

        /**
         * Whether the status overlay should cover the dropzone.
         *
         * The "ready" tone is excluded: it is a resting state shown alongside
         * the preview, and dimming the very file the message refers to reads
         * as an error.
         */
        get showOverlay() {
            return this.uploading || this.busy || (!!this.message && this.messageTone !== "ready");
        },

        get summary() {
            if (this.files.length === 0) return "";
            if (this.files.length === 1) return this.files[0].name;

            return `${this.files.length} files`;
        },

        /** Combined size of everything currently staged. */
        get totalSize() {
            const bytes = this.files.reduce((sum, file) => sum + (file.bytes || 0), 0);

            return bytes > 0 ? formatBytes(bytes) : "";
        },

        // ------------------------------------------------------------ intake

        onPick(event) {
            const chosen = [...(event.target.files || [])];
            if (chosen.length === 0) return;

            this.accepted(chosen).then((files) => {
                // Always clear the input: without this, re-picking the same
                // file after a removal fires no change event at all.
                if (this.input) this.input.value = "";

                if (files.length > 0) this.take(files);
            });
        },

        onDrop(event) {
            this.dragging = false;

            const dropped = [...(event.dataTransfer?.files || [])];
            if (dropped.length === 0) return;

            const chosen = this.multiple ? dropped : dropped.slice(0, 1);

            this.accepted(chosen).then((files) => {
                if (files.length > 0) this.take(files);
            });
        },

        openPicker() {
            if (this.uploading || this.busy) return;
            if (this.hasFiles && !this.multiple) return;

            this.input?.click();
        },

        /**
         * Filter a selection down to what this instance will accept.
         *
         * Drag-and-drop bypasses the browser's own accept filtering entirely,
         * so both paths are checked here. These checks are a convenience — the
         * server re-validates everything in UploadValidator.
         */
        async accepted(candidates) {
            const limit = this.options.maxSize > 0 ? this.options.maxSize * 1024 * 1024 : 0;
            const room = this.options.maxFiles
                ? this.options.maxFiles - this.files.length
                : Infinity;

            const kept = [];

            for (const file of candidates) {
                if (kept.length >= room) {
                    this.fail(`Only ${this.options.maxFiles} file(s) can be uploaded here.`);
                    break;
                }

                if (!matchesAccept(file, this.accept)) {
                    this.fail(`${file.name} is not an accepted file type.`);
                    continue;
                }

                if (limit && file.size > limit) {
                    this.fail(`${file.name} is larger than ${this.options.maxSize}MB.`);
                    continue;
                }

                kept.push(file);
            }

            return kept;
        },

        /** Run the accepted files through the editor and the transcoder. */
        async take(files) {
            this.busy = true;
            this.clearMessage();

            try {
                const processed = [];

                for (const file of files) {
                    const prepared = await this.prepare(file);

                    if (prepared) {
                        // Carry the original size through so the UI can report
                        // what the client-side pass actually saved.
                        prepared.originalSize = file.size;
                        processed.push(prepared);
                    }
                }

                if (processed.length === 0) return;

                this.stage(processed);

                if (this.options.autoUpload) {
                    await this.upload();
                    return;
                }

                // "ready" is a resting state, not a transient status: it renders
                // as a small pill so the preview underneath stays visible,
                // rather than as the full blurred overlay the other tones use.
                this.notify(
                    processed.length === 1 ? "Ready to upload" : `${processed.length} files ready`,
                    "ready",
                    0
                );
            } catch (error) {
                this.fail(error?.message || "That file could not be processed.");
            } finally {
                this.busy = false;
            }
        },

        /**
         * Crop (if the editor is on) then re-encode. Returns null when the
         * person cancelled out of the editor.
         */
        async prepare(file) {
            const { editor: editorOptions, image } = this.options;

            if (editorOptions.enabled && file.type.startsWith("image/")) {
                const editor = await loadEditor();

                const cropped = await editor.open(file, {
                    ...editorOptions,
                    quality: image.quality,
                    maxWidth: image.maxWidth,
                    maxHeight: image.maxHeight,

                    // The crop is the encode, so the same budget applies here
                    // rather than in a second pass that would only lose data.
                    targetSize: resolveBudget(file.size, image.targetSize, image.compress),
                });

                if (!cropped) return null;

                // The editor already emitted the requested format at the
                // requested quality, so transcoding again would only lose data.
                return cropped;
            }

            return await transcode(file, image);
        },

        stage(files) {
            if (!this.multiple) this.releaseUrls();

            const entries = files.map((file) => ({
                name: file.name,
                size: formatBytes(file.size),
                bytes: file.size,
                kind: fileKind(file),
                preview: this.previewFor(file),
                savings: this.savingsFor(file),

                // Livewire's own hash for this upload, filled in once the
                // server has it. Removing one file from a multi-file property
                // needs the server's name, not the one the browser showed.
                serverName: null,
            }));

            this.pending = this.multiple ? [...this.pending, ...files] : files;
            this.files = this.multiple ? [...this.files, ...entries] : entries;
        },

        /**
         * What the crop and re-encode pass saved, as "1.4 MB → 220 KB (−84%)".
         *
         * Off unless the caller asks for it with show-savings, so the ordinary
         * uploader stays a dropzone and a filename rather than a report. Null
         * whenever nothing was actually saved — there is no point announcing a
         * 0% reduction, and a file that grew is not a saving.
         */
        savingsFor(file) {
            if (!this.options.showSavings) return null;

            const before = file.originalSize;
            const after = file.size;

            if (!before || !after || after >= before) return null;

            const percent = Math.round((1 - after / before) * 100);

            if (percent < 1) return null;

            return {
                before: formatBytes(before),
                after: formatBytes(after),
                percent,

                // False when a target-size or compress budget was set and the
                // encoder could not reach it even after dropping resolution.
                // Reporting a reduction that missed its target as a plain
                // success would be misleading.
                met: file.budget ? file.budgetMet !== false : true,
                budget: file.budget ? formatBytes(file.budget) : null,

                text: `${formatBytes(before)} → ${formatBytes(after)} (−${percent}%)`,
            };
        },

        previewFor(file) {
            if (!this.options.preview || !file.type.startsWith("image/")) return null;

            const url = URL.createObjectURL(file);
            this.objectUrls.push(url);

            return url;
        },

        // ------------------------------------------------------------ upload

        async upload() {
            if (this.uploading || this.pending.length === 0 || !this.model) return;

            this.uploading = true;
            this.progress = 0;
            this.notify("Uploading…", "info", 0);

            const files = this.pending;

            try {
                const uploaded = await new Promise((resolve, reject) => {
                    const onProgress = (event) => {
                        this.progress = Math.round(event.detail.progress);
                    };

                    if (this.multiple) {
                        this.$wire.uploadMultiple(this.model, files, resolve, reject, onProgress);
                    } else {
                        this.$wire.upload(this.model, files[0], resolve, reject, onProgress);
                    }
                });

                this.adoptServerNames(uploaded, files.length);

                this.pending = [];
                this.progress = 100;

                // One second is enough to register. Any longer and the
                // confirmation is just hiding the file it refers to.
                this.notify("Uploaded", "success", 1000);

                this.$dispatch("af-uploader:uploaded", { id: this.id, model: this.model });
            } catch (error) {
                this.fail(this.errorText(error));
                this.$dispatch("af-uploader:failed", { id: this.id, model: this.model, error });
            } finally {
                this.uploading = false;
            }
        },

        /**
         * Actually abort the request.
         *
         * The previous implementation only flipped the UI flags, so the upload
         * ran to completion and its success callback then overwrote the
         * "cancelled" message with "Success" (AUDIT.md, HIGH-03).
         */
        cancel() {
            if (!this.uploading) return;

            this.$wire.cancelUpload?.(this.model);

            this.uploading = false;
            this.progress = 0;
            this.pending = [];
            this.releaseUrls();
            this.files = [];

            if (this.input) this.input.value = "";

            this.notify("Upload cancelled", "info");
        },

        /**
         * Record the names Livewire gave each upload.
         *
         * Removing one file from a multi-file property has to name the file
         * the *server* knows about; the client filename it was picked under
         * means nothing to Livewire.
         */
        adoptServerNames(uploaded, count) {
            const names = Array.isArray(uploaded) ? uploaded : [uploaded];

            // The last `count` entries are the ones this call just uploaded.
            const staged = this.files.slice(this.files.length - count);

            staged.forEach((entry, index) => {
                if (names[index]) entry.serverName = names[index];
            });
        },

        /**
         * Remove one file, or everything when no index is given.
         *
         * discardUpload() is what stops livewire-tmp filling up with files
         * nobody will ever claim (AUDIT.md, HIGH-04).
         */
        async removeAt(index) {
            if (this.uploading || index < 0 || index >= this.files.length) return;

            const [entry] = this.files.splice(index, 1);
            this.revoke(entry.preview);

            this.pending = this.pending.filter((file) => file.name !== entry.name);

            if (this.input) this.input.value = "";
            if (this.files.length === 0) this.clearMessage();

            if (!this.model) return;

            if (typeof this.$wire.discardUpload === "function") {
                await this.$wire.discardUpload(this.model, entry.serverName);
            } else if (Array.isArray(this.entangled)) {
                this.entangled = this.entangled.filter((_, i) => i !== index);
            }

            this.$dispatch("af-uploader:cleared", {
                id: this.id,
                model: this.model,
                file: entry.name,
            });
        },

        /** Clear the whole dropzone. */
        async clear() {
            if (this.uploading) {
                this.cancel();
                return;
            }

            this.releaseUrls();
            this.files = [];
            this.pending = [];

            if (this.input) this.input.value = "";
            this.clearMessage();

            if (!this.model) return;

            if (typeof this.$wire.discardUpload === "function") {
                await this.$wire.discardUpload(this.model, null);
            } else {
                // Component does not use the trait — fall back to clearing the
                // bound property and leave the temp file to Livewire's janitor.
                this.entangled = this.multiple ? [] : null;
            }

            this.$dispatch("af-uploader:cleared", { id: this.id, model: this.model });
        },

        // ------------------------------------------------------- model sync

        /**
         * Reflect the server's value.
         *
         * Only clearing is handled here. A freshly uploaded temporary file
         * comes back as an opaque hash with no usable URL, and the local blob
         * preview is both faster and correct, so it is left in place.
         */
        syncFromModel(value) {
            if (this.uploading || this.busy || this.pending.length > 0) return;

            const empty = value === null
                || value === undefined
                || value === ""
                || (Array.isArray(value) && value.length === 0);

            if (empty) {
                if (this.hasFiles) {
                    this.releaseUrls();
                    this.files = [];
                }
                return;
            }

            if (this.hasFiles) return;

            this.files = this.describeStored(value);
        },

        /** Build display entries for values already persisted server-side. */
        describeStored(value) {
            const items = Array.isArray(value) ? value : [value];

            return items
                .map((item) => {
                    const url = typeof item === "string"
                        ? item
                        : item?.preview_url || item?.temporary_url || item?.url || item?.path || null;

                    if (!url) return null;

                    // A bare Livewire temp hash is not addressable as a URL.
                    const isTempHash = typeof url === "string" && !url.includes("/") && /^[a-z0-9-]{20,}\./i.test(url);

                    return {
                        name: decodeURIComponent(String(url).split("/").pop() || "File"),
                        size: "",
                        kind: fileKind(url),
                        preview: !isTempHash && isDisplayableImage(url, this.accept) ? url : null,
                    };
                })
                .filter(Boolean);
        },

        // -------------------------------------------------------- messaging

        notify(text, tone = "info", autoHide = 2500) {
            clearTimeout(this.messageTimer);

            this.message = text;
            this.messageTone = tone;

            if (autoHide > 0) {
                this.messageTimer = setTimeout(() => this.clearMessage(), autoHide);
            }
        },

        fail(text) {
            this.notify(text, "error", 5000);
        },

        clearMessage() {
            clearTimeout(this.messageTimer);
            this.message = "";
            this.messageTone = "";
        },

        errorText(error) {
            if (!error) return "Upload failed.";
            if (typeof error === "string") return error;

            // Livewire hands the error callback the raw response body when
            // validation fails on the server.
            const message = error.message || error.detail?.message;

            return message || "Upload failed.";
        },

        // ------------------------------------------------------------ memory

        revoke(url) {
            if (!url || !url.startsWith("blob:")) return;

            URL.revokeObjectURL(url);
            this.objectUrls = this.objectUrls.filter((existing) => existing !== url);
        },

        releaseUrls() {
            this.objectUrls.forEach((url) => URL.revokeObjectURL(url));
            this.objectUrls = [];
        },
    };
}
