import test from 'node:test';
import assert from 'node:assert/strict';
import { mountSubmitFeedback, mountPasswordToggle, mountSidebar } from '../../resources/js/institutional-ui.js';

class Element {
    constructor() { this.attributes = new Map(); this.listeners = {}; this.classes = new Set(); this.dataset = {}; this.innerHTML = '<span>Guardar</span>'; }
    addEventListener(name, callback) { (this.listeners[name] ??= []).push(callback); }
    emit(name, event = {}) { this.listeners[name]?.forEach(callback => callback(event)); }
    setAttribute(name, value) { this.attributes.set(name, value); }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    removeAttribute(name) { this.attributes.delete(name); }
    get classList() { return { add: name => this.classes.add(name), remove: name => this.classes.delete(name) }; }
    focus() { this.owner.activeElement = this; }
    getClientRects() { return [1]; }
}

function submitFixture() {
    const root = new Element();
    const status = new Element();
    root.querySelector = () => status;
    const tasks = [];
    const reset = mountSubmitFeedback(root, task => tasks.push(task));
    const form = new Element();
    form.method = 'post';
    const button = new Element();
    const submit = () => {
        const event = { target: form, submitter: button, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
        root.emit('submit', event);
        return event;
    };
    return { form, button, status, reset, submit, flush: () => { tasks.splice(0).forEach(task => task()); } };
}

test('envío nativo muestra progreso, impide duplicados y conserva el botón en el payload', () => {
    const f = submitFixture();
    f.submit(); f.flush();
    assert.equal(f.form.getAttribute('aria-busy'), 'true');
    assert.equal(f.button.textContent, 'Procesando…');
    assert.equal(f.button.getAttribute('aria-disabled'), 'true');
    assert.equal(f.button.disabled, undefined);
    assert.equal(f.submit().defaultPrevented, true);
    assert.match(f.status.textContent, /Procesando solicitud/);
});

test('confirmación cancelada o manejador asíncrono no bloquea ni cambia el formulario', () => {
    const f = submitFixture();
    const event = f.submit(); event.preventDefault(); f.flush();
    assert.equal(f.form.getAttribute('aria-busy'), null);
    assert.equal(f.button.innerHTML, '<span>Guardar</span>');
    assert.equal(f.submit().defaultPrevented, false);
});

test('búsquedas GET y descargas en otra pestaña conservan su funcionamiento', () => {
    for (const changes of [{ method: 'get' }, { target: '_blank' }]) {
        const f = submitFixture(); Object.assign(f.form, changes);
        f.submit(); f.flush();
        assert.equal(f.form.getAttribute('aria-busy'), null);
    }
});

test('volver con historial restaura contenido y permite un nuevo envío', () => {
    const f = submitFixture();
    f.form.dataset.submitLabel = 'Adjuntando archivos…';
    f.submit(); f.flush();
    assert.equal(f.button.textContent, 'Adjuntando archivos…');
    f.reset();
    assert.equal(f.button.innerHTML, '<span>Guardar</span>');
    assert.equal(f.button.getAttribute('aria-disabled'), null);
    assert.equal(f.form.getAttribute('aria-busy'), null);
    assert.equal(f.submit().defaultPrevented, false);
});

test('contraseña se muestra solo por acción explícita y se puede volver a ocultar', () => {
    const button = new Element(); const input = { type: 'password' };
    mountPasswordToggle(button, input);
    assert.equal(button.hidden, false);
    assert.equal(input.type, 'password');
    button.emit('click');
    assert.equal(input.type, 'text');
    assert.equal(button.getAttribute('aria-pressed'), 'true');
    assert.equal(button.getAttribute('aria-label'), 'Ocultar contraseña');
    button.emit('click');
    assert.equal(input.type, 'password');
    assert.equal(button.getAttribute('aria-label'), 'Mostrar contraseña');
});

function sidebarFixture() {
    const root = new Element(); root.body = new Element();
    const elements = Object.fromEntries(['sidebar', 'sidebar-overlay', 'sidebar-open', 'sidebar-close', 'workspace-content'].map(name => [name, new Element()]));
    root.querySelector = selector => elements[selector.slice(6, -1)];
    Object.values(elements).forEach(element => { element.owner = root; });
    const link = new Element(); link.owner = root;
    elements.sidebar.querySelectorAll = () => [link, elements['sidebar-close']];
    const viewport = new Element(); viewport.matches = true;
    mountSidebar(root, viewport);
    return { root, elements, viewport, link };
}

test('menú móvil aísla el fondo, contiene Tab, cierra con Escape y devuelve el foco', () => {
    const { root, elements: e, link } = sidebarFixture();
    assert.equal(e.sidebar.inert, true);
    e['sidebar-open'].emit('click');
    assert.equal(e['workspace-content'].inert, true);
    assert.equal(e.sidebar.getAttribute('aria-modal'), 'true');
    assert.equal(root.activeElement, e['sidebar-close']);
    const tab = { key: 'Tab', preventDefault() { this.prevented = true; } };
    root.emit('keydown', tab);
    assert.equal(tab.prevented, true);
    assert.equal(root.activeElement, link);
    root.emit('keydown', { key: 'Tab', shiftKey: true, preventDefault() {} });
    assert.equal(root.activeElement, e['sidebar-close']);
    root.emit('keydown', { key: 'Escape', preventDefault() {} });
    assert.equal(e['workspace-content'].inert, false);
    assert.equal(e.sidebar.inert, true);
    assert.equal(e.sidebar.getAttribute('aria-modal'), null);
    assert.equal(root.activeElement, e['sidebar-open']);
});

test('cambio a escritorio libera el fondo y deja visible la navegación', () => {
    const { elements: e, viewport } = sidebarFixture();
    e['sidebar-open'].emit('click');
    viewport.matches = false; viewport.emit('change');
    assert.equal(e['workspace-content'].inert, false);
    assert.equal(e.sidebar.inert, false);
    assert.equal(e['sidebar-open'].getAttribute('aria-expanded'), 'false');
});
