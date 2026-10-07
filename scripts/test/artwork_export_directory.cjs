const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../public/assets/artwork-export.js'), 'utf8');
const storage = new Map();
let sequence = 0;
function page({ supported = true, cancel = false, failure = false, duplicate = false, denied = false } = {}) {
    const state = { calls: [], requests: 0, writes: [], status: {}, button: {} };
    const form = { dataset: {}, action: '/artwork/export', append() {}, querySelector: () => state.button,
        addEventListener: (_, listener) => state.submit = listener };
    vm.runInNewContext(source, {
        document: { querySelectorAll: () => [form], createElement: () => Object.assign(state.status, { style: {}, setAttribute() {} }) },
        sessionStorage: { getItem: k => storage.get(k), setItem: (k, v) => storage.set(k, v) },
        crypto: { randomUUID: () => `session-${++sequence}` },
        FormData: class {},
        window: supported ? { showDirectoryPicker: async options => {
            state.calls.push(options);
            if (cancel) throw { name: 'AbortError' };
            return { name: 'Selected folder', getFileHandle: async (name, options) => {
                if (denied) throw { name: 'NotAllowedError' };
                if (!options?.create) {
                    if (duplicate && name === 'image.png') return {};
                    throw { name: 'NotFoundError' };
                }
                return { createWritable: async () => ({ write: async bytes => state.writes.push([name, bytes]), close: async () => {} }) };
            } };
        } } : {},
        fetch: async () => {
            state.requests++;
            return { ok: !failure, headers: { get: key => key === 'Content-Type' ? 'image/png' : 'attachment; filename="image.png"' }, blob: async () => 'image bytes' };
        }
    });
    state.export = () => state.submit({ preventDefault() {} });
    return state;
}
(async () => {
    let p = page();
    await p.export();
    assert.equal(p.calls[0].startIn, 'downloads');
    const id = p.calls[0].id;
    assert.equal(p.writes[0][0], 'image.png');
    p = page({ duplicate: true });
    assert.match(p.status.textContent, /Selected folder/);
    await p.export();
    assert.equal(p.calls[0].id, id);
    assert.equal(p.calls[0].startIn, undefined);
    assert.equal(p.writes[0][0], 'image (1).png');
    p = page({ cancel: true }); await p.export();
    assert.equal(p.requests, 0); assert.equal(p.button.disabled, false);
    p = page({ failure: true }); await p.export(); assert.equal(p.writes.length, 0);
    assert.match(p.status.textContent, /Could not save/);
    p = page({ denied: true }); await p.export(); assert.equal(p.writes.length, 0);
    p = page({ supported: false }); assert.equal(p.submit, undefined);
    storage.clear(); p = page(); await p.export();
    assert.notEqual(p.calls[0].id, id); assert.equal(p.calls[0].startIn, 'downloads');
    console.log('Export directory: default, selection, session reload/reset, duplicate, cancel, failure, permission and fallback checks passed.');
})().catch(error => { console.error(error); process.exit(1); });
