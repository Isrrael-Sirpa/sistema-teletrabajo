import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { setImmediate } from 'node:timers/promises';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/multi-career-recipients.js', import.meta.url), 'utf8');
const names = ['Aeronáutica', 'Electromecánica', 'Química Industrial'];
const selector = (name) => `[data-${name}]`;

// Exercise the production event handlers with isolated server snapshots and no network.
function fixture({ failAt = 0, deferFirst = false } = {}) {
    function node(properties = {}) {
        return {
            dataset: {}, listeners: {}, childNodes: [], value: '', checked: false, disabled: false,
            textContent: '', hidden: false, ...properties,
            addEventListener(name, listener) { (this.listeners[name] ??= []).push(listener); },
            emit(name, properties = {}) {
                const event = { target: this, preventDefault() { this.defaultPrevented = true; }, ...properties };
                return Promise.all((this.listeners[name] ?? []).map((listener) => listener(event)));
            },
            setAttribute(name, value) { this[name] = value; },
            querySelectorAll() { return []; },
            replaceChildren(...children) { this.childNodes = children; },
            cloneNode() { return node({ ...this, dataset: { ...this.dataset }, listeners: {}, childNodes: this.childNodes.map((child) => child.cloneNode(true)) }); },
            matches(value) { return value === '[data-recipient-operation]' && Boolean(this.dataset.recipientOperation); },
            scrollIntoView() {}, reset() { this.resetCalled = true; },
        };
    }
    const version = node({ value: '0' });
    const fileVersion = node({ value: '0' });
    const search = node();
    const count = node();
    const submit = node({ disabled: true });
    const feedback = node();
    const retry = node({ hidden: true });
    const copy = node({ dataset: { recipientOperation: 'add-copy' }, action: '/destinatarios', payload: { recipient_type: 'unit', unit_id: '2', requires_response: '0' } });
    const copyButton = node();
    const main = node({ action: '/destinatarios', payload: {} });
    let state = [];
    let serverVersion = 0;
    const controlsFor = (rows) => rows.map((row) => node({
        dataset: { recipientOperation: 'remove' }, action: '/destinatarios',
        payload: { _method: 'DELETE', recipient_id: row.id }, button: node(),
    }));
    const sections = {};
    function snapshot(error) {
        const elements = {};
        const rowNames = state.filter((row) => row.main).map((row) => names[Number(row.id) - 1]);
        const list = node({ childNodes: state.map((row) => node({ textContent: `${names[Number(row.id) - 1]} ${row.main ? 'Destinatario principal' : 'Copia informativa'} Preparado`, row: { ...row } })) });
        const options = node({ childNodes: names.map((name, index) => {
            const existing = state.find((row) => row.id === String(index + 1));
            const choice = node({ value: String(index + 1), dataset: { careerName: name }, checked: Boolean(existing), disabled: Boolean(existing) });
            choice.closest = () => choice.option;
            choice.option = node({ textContent: `${name} ${existing ? (existing.main ? 'Ya es destinatario principal' : 'Ya está como copia') : ''}` });
            choice.cloneNode = () => {
                const clone = node({ value: choice.value, dataset: { ...choice.dataset }, checked: choice.checked, disabled: choice.disabled });
                clone.option = node({ textContent: choice.option.textContent });
                clone.closest = () => clone.option;
                return clone;
            };
            return choice;
        }) });
        const totalMain = state.filter((row) => row.main).length;
        const totalCopies = state.length - totalMain;
        elements[selector('recipient-list')] = list;
        elements[selector('recipient-counts')] = node({ childNodes: [node({ textContent: `${totalMain} ${totalMain === 1 ? 'principal' : 'principales'} · ${totalCopies} ${totalCopies === 1 ? 'copia' : 'copias'}` })] });
        elements[selector('career-options')] = options;
        elements[selector('recipient-send-summary')] = node({ childNodes: [node({ textContent: rowNames.join(', ') })] });
        elements[selector('recipient-send-controls')] = node({ childNodes: [node({ disabled: state.length === 0 })] });
        elements[selector('copy-unit-options')] = node({ childNodes: state.map((row) => node({ value: row.id, disabled: true })) });
        elements[selector('copy-person-options')] = node();
        const freshEditor = node({ dataset: { recipientEditor: 'draft-public-id' } });
        freshEditor.querySelector = () => node({ value: String(serverVersion) });
        return {
            elements,
            querySelector: (key) => key === selector('recipient-editor') ? freshEditor : key === '[role="alert"]' ? (error ? node({ textContent: error }) : null) : elements[key],
        };
    }
    Object.assign(sections, snapshot().elements);
    const choices = () => sections[selector('career-options')].childNodes;
    const rowForms = () => controlsFor(sections[selector('recipient-list')].childNodes.map((child) => child.row));
    const editor = node({ dataset: { recipientEditor: 'draft-public-id' } });
    editor.contains = (target) => target === copy || Boolean(target.dataset.recipientOperation);
    editor.querySelector = (key) => ({
        [selector('multi-career-form')]: main, [selector('recipient-feedback')]: feedback,
        [selector('recipient-sync')]: retry,
    })[key];
    editor.querySelectorAll = () => [submit, search, ...choices(), copyButton];
    main.querySelector = (key) => ({
        [selector('career-search')]: search, [selector('career-selection-count')]: count,
        [selector('career-submit')]: submit, [selector('multi-career-lock-version')]: version,
    })[key];
    main.querySelectorAll = choices;
    const document = node();
    document.querySelector = (key) => key === selector('recipient-editor') ? editor : sections[key];
    document.querySelectorAll = (key) => key.startsWith('input[') ? [version, fileVersion]
        : key === '[data-recipient-send-controls] button' ? sections[selector('recipient-send-controls')].childNodes : [];
    const requests = [];
    const visibleBeforePosts = [];
    const pages = new Map();
    let release;
    let posts = 0;
    const answer = (error) => {
        const token = String(pages.size);
        pages.set(token, snapshot(error));
        return { ok: true, text: async () => token };
    };
    class FormData {
        constructor(target) { this.values = new Map(Object.entries({ _token: 'test-csrf', ...target.payload })); }
        set(key, value) { this.values.set(key, value); }
    }
    vm.runInNewContext(source, {
        document, setTimeout: () => 1, clearTimeout() {},
        window: { location: { href: 'http://localhost/documentos/draft#recipients-title', assign() { throw new Error('Unexpected navigation'); }, reload() { throw new Error('Unexpected reload'); } } },
        FormData, DOMParser: class { parseFromString(token) { return pages.get(token); } },
        fetch: async (url, options) => {
            if (options.method !== 'POST') return answer();
            posts += 1;
            const data = Object.fromEntries(options.body.values);
            requests.push(data);
            visibleBeforePosts.push(sections[selector('recipient-list')].childNodes.map((child) => child.textContent));
            assert.equal(Number(data.lock_version), serverVersion);
            if (deferFirst && posts === 1) await new Promise((resolve) => { release = resolve; });
            if (posts === failAt) return answer('No se pudo agregar esa carrera.');
            if (data._method === 'DELETE') state = state.filter((row) => row.id !== data.recipient_id);
            else state.push({ id: data.unit_id, main: data.requires_response === '1' });
            serverVersion += 1;
            return answer();
        },
    });
    const ready = document.emit('DOMContentLoaded');
    return {
        ready, document, main, editor, copy, feedback, count, submit, search, choices, requests, version, fileVersion, sections, visibleBeforePosts,
        choose(ids) { choices().forEach((choice) => { if (! choice.disabled) choice.checked = ids.includes(choice.value); }); return main.emit('change'); },
        submitForm(target = main) { return document.emit('submit', { target }); },
        remove(id) { const target = rowForms().find((row) => row.payload.recipient_id === id); assert.ok(target); return this.submitForm(target); },
        release() { release(); },
    };
}

test('one, two and three careers immediately update the list, counters, selector and all document versions', async () => {
    for (const total of [1, 2, 3]) {
        const app = fixture();
        await app.ready;
        await app.choose(['1', '2', '3'].slice(0, total));
        await app.submitForm();
        assert.equal(app.sections[selector('recipient-list')].childNodes.length, total);
        assert.equal(app.sections[selector('recipient-counts')].childNodes[0].textContent, `${total} ${total === 1 ? 'principal' : 'principales'} · 0 copias`);
        assert.equal(app.count.textContent, '0 carreras seleccionadas.');
        assert.equal(app.version.value, String(total));
        assert.equal(app.fileVersion.value, String(total));
        assert.deepEqual(app.requests.map((request) => request.lock_version), Array.from({ length: total }, (_, index) => String(index)));
        app.choices().slice(0, total).forEach((choice) => {
            assert.equal(choice.checked, true);
            assert.equal(choice.disabled, true);
            assert.match(choice.option.textContent, /Ya es destinatario principal/);
        });
        assert.match(app.feedback.textContent, /agregad[ao]s? correctamente/);
        assert.equal(app.search.disabled, false);
        assert.equal(app.submit.disabled, true);
    }
});

test('a partial failure preserves the two confirmed additions and leaves the third selectable without reload', async () => {
    const app = fixture({ failAt: 3 });
    await app.ready;
    await app.choose(['1', '2', '3']);
    await app.submitForm();
    assert.equal(app.visibleBeforePosts[2].length, 2);
    assert.equal(app.sections[selector('recipient-list')].childNodes.length, 2);
    assert.match(app.feedback.textContent, /Se agregaron 2 de 3 carreras.*Química Industrial no pudo agregarse/);
    assert.equal(app.version.value, '2');
    assert.equal(app.choices()[2].disabled, false);
    assert.equal(app.choices()[2].checked, true);
    assert.equal(app.count.textContent, '1 carrera seleccionada.');
    assert.equal(app.submit.disabled, false);
});

test('removing a newly rendered recipient restores its option; copies also add and remove in place', async () => {
    const app = fixture();
    await app.ready;
    await app.choose(['1']);
    await app.submitForm();
    await app.remove('1');
    assert.equal(app.sections[selector('recipient-list')].childNodes.length, 0);
    assert.equal(app.choices()[0].disabled, false);
    assert.equal(app.choices()[0].checked, false);
    assert.equal(app.count.textContent, '0 carreras seleccionadas.');
    assert.equal(app.sections[selector('recipient-send-controls')].childNodes[0].disabled, true);
    assert.equal(app.version.value, '2');

    await app.submitForm(app.copy);
    assert.match(app.sections[selector('recipient-counts')].childNodes[0].textContent, /0 principales · 1 copia/);
    assert.match(app.sections[selector('recipient-list')].childNodes[0].textContent, /Copia informativa/);
    assert.equal(app.choices()[1].disabled, true);
    await app.remove('2');
    assert.equal(app.choices()[1].disabled, false);
    assert.equal(app.version.value, '4');
});

test('loading shows Agregando and blocks double submission until the request finishes', async () => {
    const app = fixture({ deferFirst: true });
    await app.ready;
    await app.choose(['1']);
    const pending = app.submitForm();
    await setImmediate();
    assert.equal(app.submit.textContent, 'Agregando...');
    assert.equal(app.submit.disabled, true);
    assert.ok(app.choices().every((choice) => choice.disabled));
    await app.submitForm();
    assert.equal(app.requests.length, 1);
    app.release();
    await pending;
    assert.equal(app.submit.textContent, 'Agregar carreras seleccionadas');
    assert.equal(app.choices()[1].disabled, false);
});
