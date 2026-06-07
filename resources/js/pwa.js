/**
 * FutGO · PWA
 * ─────────────────────────────────────────────────────────────
 * 1. Captura beforeinstallprompt de inmediato (antes de Alpine)
 * 2. Registra el Service Worker al cargar la página
 * 3. Registra el Alpine store 'pwa' en alpine:init
 */

// ── 1. Capturar el prompt nativo lo antes posible ────────────────────────
//    (beforeinstallprompt puede dispararse muy temprano, antes de Alpine)
let _deferredPrompt = null;
let _promptCaptured = false;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    _deferredPrompt = e;
    _promptCaptured = true;
    // Si el store ya está inicializado, sincronizar
    if (window.Alpine && window.Alpine.store('pwa')) {
        const s = window.Alpine.store('pwa');
        s._prompt    = e;
        s.canInstall = true;
        _pwaTrack('install_prompt_shown');
    }
});

// ── 2. Registro del Service Worker ───────────────────────────────────────
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .catch((err) => console.warn('[SW] registro fallido:', err));
    });
}

// ── 3. Alpine store 'pwa' ────────────────────────────────────────────────
document.addEventListener('alpine:init', () => {
    window.Alpine.store('pwa', {
        canInstall:    false,
        isInstalled:   false,
        showIosModal:  false,
        showToast:     false,
        isIos:         false,
        _prompt:       null,

        init() {
            // Ya instalada como PWA: ocultar el botón permanentemente
            if (
                window.matchMedia('(display-mode: standalone)').matches ||
                navigator.standalone === true
            ) {
                this.isInstalled = true;
                return;
            }

            // Detectar iOS (Safari sin MSStream = no Edge legacy)
            this.isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;

            // Sincronizar con el prompt capturado antes del init de Alpine
            if (_promptCaptured) {
                this._prompt    = _deferredPrompt;
                this.canInstall = true;
                _pwaTrack('install_prompt_shown');
            }

            // Escuchar futuros prompts (seguridad extra)
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this._prompt    = e;
                this.canInstall = true;
                _pwaTrack('install_prompt_shown');
            });

            // iOS: puede instalar manualmente → mostrar el botón
            if (this.isIos && !this.isInstalled) {
                this.canInstall = true;
            }

            // Instalación exitosa (Android)
            window.addEventListener('appinstalled', () => {
                this.canInstall  = false;
                this.isInstalled = true;
                this._showToast();
                _pwaTrack('pwa_installed');
            });
        },

        /** Lanza el flujo de instalación según plataforma */
        install() {
            _pwaTrack('install_button_clicked');

            if (this.isIos) {
                this.showIosModal = true;
                _pwaTrack('ios_install_help_opened');
                return;
            }

            if (this._prompt) {
                this._prompt.prompt();
                this._prompt.userChoice.then((choice) => {
                    if (choice.outcome === 'accepted') {
                        this.canInstall = false;
                    }
                });
            }
        },

        /** Cierra el modal de instrucciones iOS */
        closeIosModal() {
            this.showIosModal = false;
        },

        /** Muestra el toast de instalación correcta durante 4,5 s */
        _showToast() {
            this.showToast = true;
            setTimeout(() => { this.showToast = false; }, 4500);
        },
    });
});

// ── Helper de analytics ───────────────────────────────────────────────────
function _pwaTrack(eventName) {
    console.debug('[PWA]', eventName);
    if (typeof gtag !== 'undefined') {
        gtag('event', eventName, { event_category: 'pwa' });
    }
}
