/**
 * Service workers need a secure context; plain HTTP is only allowed for localhost development.
 *
 * @param {{protocol: string, hostname: string}} location
 */
export function canRegisterServiceWorker(location) {
    return location.protocol === 'https:'
        || ['localhost', '127.0.0.1', '[::1]'].includes(location.hostname);
}

/** @param {string} userAgent */
export function isIosSafari(userAgent) {
    return /iphone|ipad|ipod/i.test(userAgent)
        && /safari/i.test(userAgent)
        && !/crios|fxios|edgios|opios/i.test(userAgent);
}
