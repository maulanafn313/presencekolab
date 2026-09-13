const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

test('service worker never intercepts private URLs, even if old cache has account A data', () => {
    const listeners = {};
    let cacheAccess = 0;
    const context = { URL, self: { location: { origin: 'https://absen.test' }, addEventListener: (name, fn) => listeners[name] = fn },
        caches: { open: () => { cacheAccess++; throw new Error('Private cache accessed'); } } };
    vm.runInNewContext(fs.readFileSync('public/sw.js', 'utf8'), context);
    for (const path of ['/?ajax=get_members', '/index.php?ajax=get_today_attendance', '/api/attendance/today', '/dashboard', '/assets/file.js?action=private']) {
        listeners.fetch({ request: { url: 'https://absen.test' + path, method: 'GET', headers: new Headers(), mode: 'cors' },
            respondWith: () => assert.fail('Private response intercepted') });
    }
    assert.equal(cacheAccess, 0);
});

test('activation deletes only this application old caches', async () => {
    const listeners = {}, deleted = [];
    const source = fs.readFileSync('public/sw.js', 'utf8');
    const currentVersion = source.match(/const CACHE_NAME = '([^']+)'/)[1];
    vm.runInNewContext(fs.readFileSync('public/sw.js', 'utf8'), { URL,
        self: { location: { origin: 'https://absen.test' }, addEventListener: (name, fn) => listeners[name] = fn, clients: { claim: async () => {} } },
        caches: { keys: async () => ['absen-v1', 'another-app', currentVersion], delete: async key => deleted.push(key) } });
    let work;
    listeners.activate({ waitUntil: promise => work = promise });
    await work;
    assert.deepEqual(deleted, ['absen-v1']);
});

test('shared client preserves idempotency key across retries and sends CSRF without caching', async () => {
    const calls = [];
    const context = { URL, URLSearchParams, Headers, FormData, location: new URL('https://absen.test/presensi-masuk'),
        document: { querySelector: () => ({ content: 'csrf-test' }) },
        window: { crypto: { randomUUID: () => 'retry-key' } },
        fetch: async (url, options) => { calls.push(options); return { headers: new Headers({'Content-Type':'application/json'}), json: async () => ({ok:true}) }; } };
    vm.runInNewContext(fs.readFileSync('public/assets/js/api-client.js', 'utf8'), context);
    const payload = { nim:'TEST', mode:'masuk' };
    await context.window.api('?ajax=save_attendance', payload);
    await context.window.api('?ajax=save_attendance', payload);
    assert.equal(calls[0].body.get('request_id'), calls[1].body.get('request_id'));
    assert.equal(calls[0].headers.get('X-CSRF-TOKEN'), 'csrf-test');
    assert.equal(calls[0].cache, 'no-store');
});
