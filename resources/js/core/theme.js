/**
 * Works out whether the uploader should be light or dark, and keeps it in step
 * with whatever the host application does.
 *
 * Why this cannot be pure CSS
 * ---------------------------
 * A stylesheet can read `prefers-color-scheme`, and it can match `.dark` or
 * `[data-bs-theme="dark"]`. What it cannot do is tell the difference between
 * "this app has no opinion about theming" and "this app has chosen light".
 *
 * Tailwind's class strategy expresses light as the *absence* of `.dark`. So on
 * a machine set to dark mode, a Tailwind app sitting in light mode looks
 * identical, to CSS, to an app that never thought about theming — and a
 * `prefers-color-scheme` rule would wrongly turn the uploader dark inside a
 * light page.
 *
 * Resolving it in JavaScript removes the ambiguity: the host's own convention
 * is consulted first and only falls back to the OS when the host really is
 * silent. The answer is written to `data-af-theme` on <html>, which is the only
 * thing the stylesheet keys off.
 */

const DARK = "dark";
const LIGHT = "light";

/** Attributes worth watching, in precedence order. */
const THEME_ATTRIBUTES = [
    "data-bs-theme",     // Bootstrap 5.3
    "data-theme",        // DaisyUI, Flowbite, many hand-rolled setups
    "data-color-mode",   // Primer
    "data-color-scheme",
    "data-mode",
];

const DARK_CLASSES = ["dark", "dark-mode", "theme-dark", "is-dark"];
const LIGHT_CLASSES = ["light", "light-mode", "theme-light", "is-light"];

let mode = "auto";
let observer = null;
let mediaQuery = null;
let started = false;

/**
 * Begin syncing. Safe to call repeatedly — only the first call takes effect,
 * so every uploader on the page can ask for it without coordinating.
 *
 * @param {"auto"|"system"|"dark"|"light"} preferred
 */
export function ensureThemeSync(preferred) {
    // The module starts syncing on load, before the configured mode is known,
    // so the page never flashes the wrong palette. When the first uploader
    // later reports what config asked for, adopt it and re-resolve.
    if (preferred && preferred !== mode) {
        mode = preferred;

        if (started) {
            apply();

            return;
        }
    }

    if (started) return;
    started = true;

    apply();
    watch();

    // A host that swaps <body> during navigation takes its theme class with
    // it, so re-resolve once the new document is in place.
    document.addEventListener("livewire:navigated", apply);
    document.addEventListener("turbo:load", apply);

    // Let an application drive the uploader from its own theme toggle.
    window.AFUploaderTheme = {
        get mode() {
            return mode;
        },
        get resolved() {
            return document.documentElement.getAttribute("data-af-theme");
        },
        set(next) {
            mode = ["dark", "light", "system"].includes(next) ? next : "auto";
            apply();
        },
        refresh: apply,
    };
}

function apply() {
    const resolved = resolve();
    const root = document.documentElement;

    if (root.getAttribute("data-af-theme") !== resolved) {
        root.setAttribute("data-af-theme", resolved);
    }
}

function resolve() {
    if (mode === DARK || mode === LIGHT) return mode;
    if (mode === "system") return systemPrefersDark() ? DARK : LIGHT;

    // auto: the host's own convention wins, the OS is the tiebreaker.
    return detectHostTheme() ?? (systemPrefersDark() ? DARK : LIGHT);
}

function systemPrefersDark() {
    return window.matchMedia?.("(prefers-color-scheme: dark)").matches ?? false;
}

/**
 * Read the host's declared theme from <html> and <body>.
 *
 * @returns {"dark"|"light"|null} null when the host expresses no preference.
 */
export function detectHostTheme() {
    for (const element of [document.documentElement, document.body]) {
        if (!element) continue;

        for (const attribute of THEME_ATTRIBUTES) {
            const value = element.getAttribute(attribute)?.trim().toLowerCase();

            // A named theme such as data-theme="cupcake" says nothing about
            // light or dark, so it is passed over rather than guessed at.
            if (value === DARK || value === LIGHT) return value;
        }

        for (const name of DARK_CLASSES) {
            if (element.classList.contains(name)) return DARK;
        }

        for (const name of LIGHT_CLASSES) {
            if (element.classList.contains(name)) return LIGHT;
        }
    }

    return null;
}

function watch() {
    mediaQuery = window.matchMedia?.("(prefers-color-scheme: dark)");

    if (mediaQuery) {
        // Safari below 14 only has the deprecated listener.
        if (typeof mediaQuery.addEventListener === "function") {
            mediaQuery.addEventListener("change", apply);
        } else if (typeof mediaQuery.addListener === "function") {
            mediaQuery.addListener(apply);
        }
    }

    if (typeof MutationObserver === "undefined") return;

    observer = new MutationObserver(apply);

    // data-af-theme is deliberately absent from the filter: apply() writes it,
    // and watching it would make the observer retrigger itself.
    const options = { attributes: true, attributeFilter: ["class", ...THEME_ATTRIBUTES] };

    observer.observe(document.documentElement, options);

    if (document.body) {
        observer.observe(document.body, options);
    } else {
        document.addEventListener("DOMContentLoaded", () => {
            if (document.body) observer.observe(document.body, options);
            apply();
        }, { once: true });
    }
}
