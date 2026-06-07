import './bootstrap';
import Alpine from 'alpinejs';
import './pwa';

// ── Tema FutGO (dark-first, persistente en localStorage) ──────────────
// El anti-FOUC del <head> ya aplicó data-theme antes de pintar; este store
// gestiona el toggle en runtime y mantiene la preferencia sincronizada.
document.addEventListener('alpine:init', () => {
    Alpine.store('theme', {
        mode: document.documentElement.getAttribute('data-theme') || 'light',
        toggle() {
            this.mode = this.mode === 'light' ? 'dark' : 'light';
            localStorage.setItem('futgo-theme', this.mode);
            document.documentElement.setAttribute('data-theme', this.mode);
        },
        get isDark() {
            return this.mode !== 'light';
        },
    });
});

window.Alpine = Alpine;
Alpine.start();
