export class LivewireAdapter {
    constructor(app) {
        this.app = app;
        this.initialized = false;
        this.init();
    }

    init() {
        // Support for Livewire v3+
        const initHandler = () => {
            if (this.initialized) return;
            this.initialized = true;
            
            // Listen for morph updates to re-scan
            if (typeof Livewire !== 'undefined') {
                Livewire.hook('morph.updated', ({ el, component }) => {
                    this.app.scan();
                });

                // Handle successful syncs from server
                Livewire.on('af-upload-success', (event) => {
                    const data = Array.isArray(event) ? event[0] : event;
                    const inputId = data.inputId;
                    const input = document.getElementById(inputId);
                    if (input) {
                        this.app.showStatus(input, "Stored Successfully", "success");
                    }
                });

                Livewire.on('af-upload-error', (event) => {
                    const data = Array.isArray(event) ? event[0] : event;
                    const inputId = data.inputId;
                    const message = data.message || "Upload Failed";
                    const input = document.getElementById(inputId);
                    if (input) {
                        this.app.showStatus(input, message, "danger");
                    }
                });
            }
        };
        
        document.addEventListener("livewire:init", initHandler);
        
        // Also handle if Livewire is already initialized
        if (typeof Livewire !== 'undefined') {
            initHandler();
        }
        
        // Handle wire:navigate - re-scan after navigation
        document.addEventListener("livewire:navigated", () => {
            this.app.scan();
        });
    }
}
