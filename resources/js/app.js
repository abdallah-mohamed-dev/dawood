import Alpine from 'alpinejs';

window.Alpine = Alpine;

// A show/hide toggle whose state is remembered per browser under `key`.
// localStorage can throw (private mode, blocked storage), so both reads and
// writes fall back silently — the toggle still works, it just won't persist.
Alpine.data('persistedToggle', (key, fallback = true) => ({
    open: readFlag(key, fallback),

    toggle() {
        this.open = ! this.open;
        writeFlag(key, this.open);
    },
}));

function readFlag(key, fallback) {
    try {
        const stored = localStorage.getItem(key);

        return stored === null ? fallback : stored === 'true';
    } catch (error) {
        return fallback;
    }
}

function writeFlag(key, value) {
    try {
        localStorage.setItem(key, String(value));
    } catch (error) {
        // Storage unavailable — nothing to remember, nothing to do.
    }
}

Alpine.start();
