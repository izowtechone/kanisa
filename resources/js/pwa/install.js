import { canRegisterServiceWorker, isIosSafari } from './support';

const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || !canRegisterServiceWorker(window.location)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}

function initInstallButton() {
    const container = document.querySelector('[data-pwa-install]');

    if (!container || isStandalone()) {
        return;
    }

    const button = container.querySelector('[data-pwa-install-button]');
    const hint = container.querySelector('[data-pwa-install-hint]');
    let deferredPrompt = null;

    const hide = () => container.classList.add('hidden');

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        hint.classList.add('hidden');
        container.classList.remove('hidden');
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        hide();
    });

    if (isIosSafari(window.navigator.userAgent)) {
        hint.textContent = container.dataset.iosHint ?? '';
        container.classList.remove('hidden');
    }

    button.addEventListener('click', async () => {
        if (!deferredPrompt) {
            hint.classList.toggle('hidden');

            return;
        }

        const prompt = deferredPrompt;
        deferredPrompt = null;
        prompt.prompt();
        const { outcome } = await prompt.userChoice;

        if (outcome === 'accepted') {
            hide();
        }
    });
}

export function initPwa() {
    registerServiceWorker();
    initInstallButton();
}
