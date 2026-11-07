import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';
import { canRegisterServiceWorker, isIosSafari } from '../../resources/js/pwa/support.js';

function loadServiceWorker({ fetchImpl } = {}) {
    const listeners = {};
    const stores = new Map();
    const puts = [];
    const deleted = [];

    const cacheFor = (name) => {
        if (!stores.has(name)) {
            stores.set(name, new Map());
        }
        const store = stores.get(name);

        return {
            addAll: async (urls) => urls.forEach((url) => store.set(new URL(url, 'https://church.test').href, { cached: url })),
            match: async (request) => store.get(typeof request === 'string' ? new URL(request, 'https://church.test').href : request.url),
            put: async (request, response) => {
                puts.push(request.url);
                store.set(request.url, response);
            },
        };
    };

    const context = {
        URL,
        self: {
            location: { origin: 'https://church.test' },
            addEventListener: (type, handler) => { listeners[type] = handler; },
            skipWaiting: async () => {},
            clients: { claim: async () => {} },
        },
        caches: {
            open: async (name) => cacheFor(name),
            match: async (url) => {
                for (const store of stores.values()) {
                    const hit = store.get(new URL(url, 'https://church.test').href);
                    if (hit) {
                        return hit;
                    }
                }
            },
            keys: async () => [...stores.keys(), 'kkkt-static-old', 'other-app'],
            delete: async (name) => { deleted.push(name); return true; },
        },
        fetch: fetchImpl ?? (async () => ({ ok: true, type: 'basic', clone() { return this; } })),
    };

    vm.runInNewContext(readFileSync(new URL('../../public/sw.js', import.meta.url), 'utf8'), context);

    const dispatch = async (request) => {
        let responded = null;
        await listeners.fetch({ request, respondWith: (promise) => { responded = promise; } });

        return responded ? await responded : null;
    };

    return { listeners, stores, puts, deleted, dispatch };
}

const req = (path, extra = {}) => ({
    url: `https://church.test${path}`, method: 'GET', mode: 'cors', destination: '', ...extra,
});

test('install precaches the offline page', async () => {
    const sw = loadServiceWorker();
    let work;
    sw.listeners.install({ waitUntil: (p) => { work = p; } });
    await work;

    assert.ok([...sw.stores.values()][0].has('https://church.test/offline.html'));
});

test('activate deletes outdated kkkt caches only', async () => {
    const sw = loadServiceWorker();
    let work;
    sw.listeners.activate({ waitUntil: (p) => { work = p; } });
    await work;

    assert.deepEqual(sw.deleted, ['kkkt-static-old']);
});

test('non-GET requests are never intercepted', async () => {
    const sw = loadServiceWorker();

    for (const method of ['POST', 'PUT', 'PATCH', 'DELETE']) {
        assert.equal(await sw.dispatch(req('/finance/transactions', { method })), null);
    }
});

test('data, document and upload requests are not intercepted or cached', async () => {
    const sw = loadServiceWorker();

    for (const path of ['/members', '/finance/receipts/1', '/storage/church-logos/a.png', '/login', '/members/lookup?search=ab']) {
        assert.equal(await sw.dispatch(req(path, { destination: 'empty' })), null, path);
        assert.equal(await sw.dispatch(req(path, { destination: 'image' })), null, path);
    }
    assert.deepEqual(sw.puts, []);
});

test('build assets are cached on first use and served from cache afterwards', async () => {
    let fetches = 0;
    const sw = loadServiceWorker({ fetchImpl: async () => { fetches++; return { ok: true, type: 'basic', clone() { return this; } }; } });
    const asset = req('/build/assets/app-abc.css', { destination: 'style' });

    await sw.dispatch(asset);
    await sw.dispatch(asset);

    assert.equal(fetches, 1);
    assert.deepEqual(sw.puts, [asset.url]);
});

test('page navigations always use the network and are never cached', async () => {
    const sw = loadServiceWorker();
    await sw.dispatch(req('/dashboard', { mode: 'navigate', destination: 'document' }));

    assert.deepEqual(sw.puts, []);
});

test('failed navigation falls back to the offline page', async () => {
    const sw = loadServiceWorker({ fetchImpl: async () => { throw new TypeError('offline'); } });
    let work;
    sw.listeners.install({ waitUntil: (p) => { work = p; } });
    await work;

    const response = await sw.dispatch(req('/dashboard', { mode: 'navigate', destination: 'document' }));

    assert.deepEqual(response, { cached: '/offline.html' });
});

test('service worker registers only on https or localhost', () => {
    assert.equal(canRegisterServiceWorker({ protocol: 'https:', hostname: 'kkkt.example.com' }), true);
    assert.equal(canRegisterServiceWorker({ protocol: 'http:', hostname: 'localhost' }), true);
    assert.equal(canRegisterServiceWorker({ protocol: 'http:', hostname: 'kkkt.example.com' }), false);
});

test('iOS Safari is detected but iOS Chrome is not', () => {
    assert.equal(isIosSafari('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1'), true);
    assert.equal(isIosSafari('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 CriOS/120.0 Mobile/15E148 Safari/604.1'), false);
    assert.equal(isIosSafari('Mozilla/5.0 (Linux; Android 14) Chrome/120 Mobile Safari/537.36'), false);
});
