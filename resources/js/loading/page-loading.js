import { shouldTrackLinkClick } from './navigation';

const FORM_TIMEOUT_MS = 60000;
const LINK_TIMEOUT_MS = 15000;

const bar = () => document.getElementById('kkkt-page-loader');
const status = () => document.getElementById('kkkt-page-status');

let activeCount = 0;
let hideTimer = null;
const busyForms = new Map();

function show(timeout) {
    activeCount++;
    bar()?.classList.remove('hidden');
    document.body.setAttribute('aria-busy', 'true');

    const label = status();
    if (label) {
        label.textContent = label.dataset.message ?? '';
    }

    clearTimeout(hideTimer);
    hideTimer = setTimeout(reset, timeout);
}

function hideAll() {
    activeCount = 0;
    clearTimeout(hideTimer);
    bar()?.classList.add('hidden');
    document.body.removeAttribute('aria-busy');

    const label = status();
    if (label) {
        label.textContent = '';
    }
}

function releaseForm(form, state) {
    state.disabled.forEach((button) => {
        button.disabled = false;
    });
    state.spinner?.remove();
    form.removeAttribute('aria-busy');
}

function reset() {
    busyForms.forEach((state, form) => releaseForm(form, state));
    busyForms.clear();
    hideAll();
}

function handleSubmit(event) {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.dataset.noLoading !== undefined
        || form.dataset.loading === 'off' || form.method === 'dialog'
        || (form.target && form.target !== '_self')) {
        return;
    }

    if (busyForms.has(form)) {
        event.preventDefault();

        return;
    }

    if (event.defaultPrevented) {
        return;
    }

    const buttons = [...form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]')];
    const submitter = event.submitter ?? buttons[0];
    const state = { disabled: [], spinner: null };

    if (submitter && submitter.tagName === 'BUTTON'
        && !submitter.querySelector('.kkkt-loader, .animate-spin')) {
        state.spinner = document.createElement('span');
        state.spinner.className = 'kkkt-loader ml-2 align-middle';
        state.spinner.setAttribute('aria-hidden', 'true');
        submitter.append(state.spinner);
    }

    form.setAttribute('aria-busy', 'true');
    busyForms.set(form, state);
    show(FORM_TIMEOUT_MS);

    // Disabled after dispatch so the submitter's name/value is still sent.
    setTimeout(() => {
        if (!busyForms.has(form)) {
            return;
        }

        buttons.forEach((button) => {
            if (!button.disabled) {
                button.disabled = true;
                state.disabled.push(button);
            }
        });
    }, 0);
}

function handleClick(event) {
    const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;
    const link = anchor ? {
        href: anchor.getAttribute('href'),
        target: anchor.getAttribute('target') ?? '',
        download: anchor.hasAttribute('download'),
        noLoading: anchor.dataset.noLoading !== undefined,
    } : null;

    if (shouldTrackLinkClick(event, link, window.location.href)) {
        show(LINK_TIMEOUT_MS);
    }
}

export function initPageLoading() {
    document.addEventListener('click', handleClick);
    document.addEventListener('submit', handleSubmit);

    window.addEventListener('pageshow', (event) => {
        const hadBusyForm = busyForms.size > 0;
        reset();

        if (event.persisted && hadBusyForm) {
            window.location.reload();
        }
    });
}
