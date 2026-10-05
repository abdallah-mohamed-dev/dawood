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

// Keeps the scroll position across form submits: every form saves where the
// page was before it leaves, and the next load of the same path jumps back
// there once and forgets it. Plain window scroll, no Alpine — so it works on
// every page. Storage can throw, so every access is guarded.
document.addEventListener('submit', () => {
    try {
        sessionStorage.setItem('scroll:' + window.location.pathname, String(window.scrollY));
    } catch (error) {
        // Storage unavailable — the page just won't jump back.
    }
}, true);

try {
    const key = 'scroll:' + window.location.pathname;
    const saved = sessionStorage.getItem(key);

    if (saved !== null) {
        sessionStorage.removeItem(key);
        window.scrollTo({ top: Number(saved), behavior: 'instant' });
    }
} catch (error) {
    // Storage unavailable — stay at the top, as before.
}

// The reminder banner stays hidden for the rest of the day once dismissed.
// The key carries today's date, so it comes back the next day by itself.
// Storage can throw, so reads and writes are guarded.
Alpine.data('reminderBanner', () => {
    const key = 'reminders.dismissed.' + new Date().toISOString().slice(0, 10);

    return {
        dismissed: readFlag(key, false),

        dismiss() {
            this.dismissed = true;
            writeFlag(key, true);
        },
    };
});

Alpine.start();
