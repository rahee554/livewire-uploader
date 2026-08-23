import { createUploader } from "./core/UploadController.js";
import { ensureThemeSync } from "./core/theme.js";

/**
 * AF Uploader entry point.
 *
 * Registers a single Alpine component. Everything else — state, uploads, the
 * editor — hangs off that one registration.
 *
 * The previous build shipped its Alpine code as a 30 KB inline <script> inside
 * a Blade partial and assigned window.afUploader directly, which meant the
 * function had to exist before Alpine evaluated any x-data on the page and
 * broke whenever the script tag landed after Livewire's. Registering through
 * Alpine.data() removes that ordering constraint entirely.
 */

const NAME = "afUploader";

function register(Alpine) {
    if (Alpine.__afUploaderRegistered) return;

    Alpine.__afUploaderRegistered = true;

    Alpine.data(NAME, (options, entangled = null) => {
        // The first uploader to initialise starts theme syncing; subsequent
        // calls are no-ops. Resolving it here rather than at module load means
        // the mode from config/af-uploader.php is already available.
        ensureThemeSync(options?.theme);

        return createUploader(options, entangled);
    });
}

// Resolve the palette immediately, not at alpine:init, so the uploader is
// never painted in the wrong theme first.
ensureThemeSync();

if (window.Alpine) {
    // Alpine already started — this module loaded late.
    register(window.Alpine);
} else {
    document.addEventListener("alpine:init", () => register(window.Alpine), { once: true });
}

export { createUploader };
