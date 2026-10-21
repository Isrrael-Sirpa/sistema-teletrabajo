import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { setImmediate } from 'node:timers/promises';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/document-file-viewer.js', import.meta.url), 'utf8');

// Minimal DOM boundary for testing navigation, revoked content and request races.
// There is no network, institutional data or browser authentication in this fixture.
function fixture(fetchResponse) {
    const document = { body: { style: { overflow: 'auto' } }, activeElement: null };
    const element = (dataset = {}) => ({
        dataset, listeners: {}, attributes: {}, children: [], disabled: false, textContent: '',
        addEventListener(name, listener) { (this.listeners[name] ??= []).push(listener); },
        emit(name, properties = {}) {
            const event = { preventDefault() { this.defaultPrevented = true; }, ...properties };
            for (const listener of this.listeners[name] ?? []) listener(event);
            return event;
        },
        focus() { document.activeElement = this; },
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
        getClientRects() { return [{}]; },
        append(child) { this.children.push(child); },
        replaceChildren() { this.children = []; },
    });
    const items = ['application/pdf', 'image/png', 'application/msword'].map((mime, index) => element({
        fileId: String(index), fileName: ['solicitud.pdf', 'respaldo.png', 'antecedentes.doc'][index],
        fileMime: mime, fileSupported: index < 2 ? '1' : '0',
        filePreviewUrl: `/privado/${index}?presentation=inline`, fileDownloadUrl: `/privado/${index}`,
    }));
    const openers = items.map((item) => element({ fileViewerOpen: item.dataset.fileId }));
    const fields = Object.fromEntries(['name', 'count', 'status', 'content', 'download', 'previous', 'next', 'close']
        .map((name) => [`[data-file-viewer-${name}]`, element()]));
    const dialog = element();
    dialog.querySelector = (selector) => fields[selector];
    dialog.querySelectorAll = (selector) => selector === '[data-file-viewer-item]' ? items : [
        fields['[data-file-viewer-close]'], ...items, ...fields['[data-file-viewer-content]'].children,
        fields['[data-file-viewer-previous]'], fields['[data-file-viewer-download]'], fields['[data-file-viewer-next]'],
    ].filter((item) => ! item.disabled);
    dialog.showModal = () => { dialog.open = true; fields['[data-file-viewer-close]'].focus(); };
    dialog.close = () => { dialog.open = false; dialog.emit('close'); };
    document.querySelector = () => dialog;
    document.querySelectorAll = () => openers;
    document.createElement = (tagName) => ({ ...element(), tagName });
    document.addEventListener = (name, listener) => { if (name === 'DOMContentLoaded') listener(); };
    const requests = [];
    const createdUrls = [];
    const revokedUrls = [];
    vm.runInNewContext(source, {
        document, AbortController, TypeError,
        fetch: (url, options) => {
            requests.push({ url, options });
            return fetchResponse(url, options);
        },
        URL: {
            createObjectURL: () => {
                const url = `blob:test-${createdUrls.length}`;
                createdUrls.push(url);
                return url;
            },
            revokeObjectURL: (url) => { revokedUrls.push(url); },
        },
    });
    return { document, dialog, items, openers, requests, createdUrls, revokedUrls,
        field: (name) => fields[`[data-file-viewer-${name}]`] };
}

const response = (mime, status = 200) => ({
    ok: status === 200, status, headers: { get: () => mime }, blob: async () => ({}),
});

test('navigates PDF, image and fallback; closes and revokes content without another request for Office', async () => {
    const app = fixture(async (url) => response(url.includes('/0?') ? 'application/pdf' : 'image/png'));
    app.openers[0].emit('click');
    await setImmediate();
    assert.equal(app.dialog.open, true);
    assert.equal(app.field('content').children[0].tagName, 'iframe');
    assert.equal(app.field('previous').disabled, true);
    assert.equal(app.requests[0].options.credentials, 'same-origin');
    assert.equal(app.requests[0].options.cache, 'no-store');

    app.field('next').emit('click');
    await setImmediate();
    assert.equal(app.field('content').children[0].tagName, 'img');
    assert.equal(app.field('count').textContent, 'Archivo 2 de 3');
    assert.deepEqual(app.revokedUrls, ['blob:test-0']);
    app.field('next').emit('click');
    await setImmediate();
    assert.match(app.field('status').textContent, /no puede visualizarse directamente/);
    assert.equal(app.field('next').disabled, true);
    assert.equal(app.field('content').children.length, 0);
    assert.equal(app.requests.length, 2);
    assert.equal(app.field('download').href, '/privado/2');

    app.field('previous').emit('click');
    await setImmediate();
    assert.equal(app.field('name').textContent, 'respaldo.png');
    app.field('close').emit('click');
    assert.equal(app.dialog.open, false);
    assert.equal(app.field('content').children.length, 0);
    assert.equal(app.document.activeElement, app.openers[0]);
    assert.equal(app.document.body.style.overflow, 'auto');
    assert.deepEqual(app.revokedUrls, app.createdUrls);
});

test('a late response cannot replace the selected file or populate a closed viewer', async () => {
    let finishFirst;
    const firstResponse = new Promise((resolve) => { finishFirst = resolve; });
    const app = fixture((url) => url.includes('/0?') ? firstResponse : Promise.resolve(response('image/png')));
    app.openers[0].emit('click');
    app.items[1].emit('click');
    await setImmediate();
    assert.equal(app.requests[0].options.signal.aborted, true);
    assert.equal(app.field('content').children[0].tagName, 'img');
    finishFirst(response('application/pdf'));
    await setImmediate();
    assert.equal(app.field('content').children[0].tagName, 'img');
    assert.equal(app.createdUrls.length, 1);
    app.field('close').emit('click');
    assert.deepEqual(app.revokedUrls, app.createdUrls);

    let finishClosed;
    const closedApp = fixture(() => new Promise((resolve) => { finishClosed = resolve; }));
    closedApp.openers[0].emit('click');
    closedApp.field('close').emit('click');
    finishClosed(response('application/pdf'));
    await setImmediate();
    assert.equal(closedApp.field('content').children.length, 0);
    assert.equal(closedApp.createdUrls.length, 0);
});

test('denied or redirected HTML content is never embedded', async () => {
    for (const reply of [response('text/html', 403), response('text/html', 404), response('text/html')]) {
        const app = fixture(async () => reply);
        app.openers[0].emit('click');
        await setImmediate();
        assert.equal(app.field('content').children.length, 0);
        assert.equal(app.createdUrls.length, 0);
        assert.notEqual(app.field('status').textContent, 'Cargando archivo…');
    }
});

test('Tab and Shift+Tab remain inside the modal at the focus boundaries', async () => {
    const app = fixture(async () => response('application/pdf'));
    app.openers[2].emit('click');
    await setImmediate();
    app.field('download').focus();
    assert.equal(app.dialog.emit('keydown', { key: 'Tab', shiftKey: false }).defaultPrevented, true);
    assert.equal(app.document.activeElement, app.field('close'));
    assert.equal(app.dialog.emit('keydown', { key: 'Tab', shiftKey: true }).defaultPrevented, true);
    assert.equal(app.document.activeElement, app.field('download'));
});
