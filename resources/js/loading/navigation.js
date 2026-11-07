/**
 * Decides whether a click on a link starts a normal same-site page navigation that should show the page loader.
 *
 * @param {{defaultPrevented: boolean, button: number, metaKey: boolean, ctrlKey: boolean, shiftKey: boolean, altKey: boolean}} event
 * @param {{href: string, target: string, download: boolean, noLoading: boolean} | null} link
 * @param {string} currentHref
 */
export function shouldTrackLinkClick(event, link, currentHref) {
    if (!link || event.defaultPrevented || event.button !== 0
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }

    if (link.download || link.noLoading || (link.target && link.target !== '_self')) {
        return false;
    }

    let destination;
    let current;

    try {
        current = new URL(currentHref);
        destination = new URL(link.href, current);
    } catch {
        return false;
    }

    if (!['http:', 'https:'].includes(destination.protocol) || destination.origin !== current.origin) {
        return false;
    }

    const samePage = destination.pathname === current.pathname && destination.search === current.search;

    return !(samePage && destination.hash !== '');
}
