import assert from 'node:assert/strict';
import { test } from 'node:test';
import { latestRequest } from '../../resources/js/loading/latest-request.js';
import { shouldTrackLinkClick } from '../../resources/js/loading/navigation.js';

const deferred = () => {
    let resolve;
    let reject;
    const promise = new Promise((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return { promise, resolve, reject };
};

test('pending is true while a request runs and false after it completes', async () => {
    const req = latestRequest();
    const gate = deferred();
    const running = req.run(() => gate.promise);

    assert.equal(req.pending, true);
    gate.resolve('ok');

    assert.deepEqual(await running, { stale: false, value: 'ok' });
    assert.equal(req.pending, false);
});

test('overlapping requests keep the loader visible until the newest finishes and ignore stale results', async () => {
    const req = latestRequest();
    const first = deferred();
    const second = deferred();
    const firstRun = req.run(() => first.promise);
    const secondRun = req.run(() => second.promise);

    first.resolve('old');
    assert.deepEqual(await firstRun, { stale: true });
    assert.equal(req.pending, true);

    second.resolve('new');
    assert.deepEqual(await secondRun, { stale: false, value: 'new' });
    assert.equal(req.pending, false);
});

test('a newer request aborts the previous one', async () => {
    const req = latestRequest();
    let firstSignal;
    const firstRun = req.run((signal) => {
        firstSignal = signal;

        return new Promise((_, reject) => signal.addEventListener('abort', () => reject(new Error('aborted'))));
    });

    await req.run(async () => 'second');

    assert.equal(firstSignal.aborted, true);
    assert.deepEqual(await firstRun, { stale: true });
    assert.equal(req.pending, false);
});

test('a failed request rethrows and clears pending', async () => {
    const req = latestRequest();

    await assert.rejects(req.run(async () => {
        throw new Error('network');
    }), /network/);
    assert.equal(req.pending, false);
});

test('cancel clears pending and discards the result', async () => {
    const req = latestRequest();
    const gate = deferred();
    const running = req.run(() => gate.promise);

    req.cancel();
    gate.resolve('late');

    assert.equal(req.pending, false);
    assert.deepEqual(await running, { stale: true });
});

const plain = { defaultPrevented: false, button: 0, metaKey: false, ctrlKey: false, shiftKey: false, altKey: false };
const link = (href, extra = {}) => ({ href, target: '', download: false, noLoading: false, ...extra });
const here = 'https://church.test/members?page=1';

test('same-site link clicks are tracked', () => {
    assert.equal(shouldTrackLinkClick(plain, link('/finance'), here), true);
    assert.equal(shouldTrackLinkClick(plain, link('https://church.test/users'), here), true);
});

test('modified, prevented, download, opt-out, new-tab and external clicks are ignored', () => {
    assert.equal(shouldTrackLinkClick({ ...plain, ctrlKey: true }, link('/finance'), here), false);
    assert.equal(shouldTrackLinkClick({ ...plain, button: 1 }, link('/finance'), here), false);
    assert.equal(shouldTrackLinkClick({ ...plain, defaultPrevented: true }, link('/finance'), here), false);
    assert.equal(shouldTrackLinkClick(plain, link('/x', { download: true }), here), false);
    assert.equal(shouldTrackLinkClick(plain, link('/x', { noLoading: true }), here), false);
    assert.equal(shouldTrackLinkClick(plain, link('/x', { target: '_blank' }), here), false);
    assert.equal(shouldTrackLinkClick(plain, link('https://other.test/'), here), false);
    assert.equal(shouldTrackLinkClick(plain, link('mailto:a@b.c'), here), false);
    assert.equal(shouldTrackLinkClick(plain, link('#main-content'), here), false);
    assert.equal(shouldTrackLinkClick(plain, null, here), false);
});
