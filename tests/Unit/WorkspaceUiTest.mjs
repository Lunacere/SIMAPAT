import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../resources/js/workspace.js', import.meta.url), 'utf8');
const formsSource = fs.readFileSync(new URL('../../resources/js/form-interactions.js', import.meta.url), 'utf8');

class Element {
    constructor(tag = 'DIV', text = '') {
        this.tagName = tag;
        this.textContent = text;
        this.children = text ? [{textContent: text}] : [];
        this.childNodes = this.children;
        this.attributes = new Map();
        this.listeners = {};
        this.hidden = false;
        this.isConnected = true;
        this.value = '';
        this.open = false;
        this.queries = {};
    }
    setAttribute(key, value) { this.attributes.set(key, value); }
    getAttribute(key) { return this.attributes.get(key) ?? null; }
    removeAttribute(key) { this.attributes.delete(key); }
    hasAttribute(key) { return this.attributes.has(key); }
    querySelector(selector) { const value = this.queries[selector]; return Array.isArray(value) ? value[0] : value ?? null; }
    querySelectorAll(selector) { const value = this.queries[selector]; return value ? (Array.isArray(value) ? value : [value]) : []; }
    closest() { return null; }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren(...nodes) { this.children = nodes; this.childNodes = nodes; this.textContent = nodes.map(node => node.textContent).join(''); }
    addEventListener(type, callback) { (this.listeners[type] ??= []).push(callback); }
    emit(type, event = {}) { for (const callback of this.listeners[type] ?? []) callback(event); }
    focus() { this.focused = true; }
    click() { this.clicked = (this.clicked ?? 0) + 1; this.emit('click'); }
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
    remove() { this.removed = true; }
}

function setup(labels = [], {fallback = false, allowFallback = false} = {}) {
    const search = new Element('INPUT');
    const results = new Element();
    const count = new Element();
    const empty = new Element('P');
    const quicknav = new Element('DIALOG');
    quicknav.queries = {'input': search, '[data-quicknav-results]': results, '[data-quicknav-count]': count, '[data-quicknav-empty]': empty, '[data-close-dialog]': new Element('BUTTON')};
    const trigger = new Element('BUTTON');
    trigger.hidden = true;
    const confirmation = new Element('DIALOG');
    const action = new Element('BUTTON');
    const cancel = new Element('BUTTON');
    const title = new Element('H2');
    const message = new Element('P');
    confirmation.queries = {'[data-confirm-action]': action, '[data-close-dialog]': cancel, '[data-confirm-title]': title, '[data-confirm-message]': message};
    const originals = labels.map((label, index) => Object.assign(new Element('A', label), {href: '/page-' + index}));
    const document = new Element();
    document.activeElement = trigger;
    document.createElement = tag => new Element(tag.toUpperCase());
    document.getElementById = id => id === 'quicknav-dialog' ? quicknav : (id === 'confirm-dialog' && !fallback ? confirmation : null);
    document.querySelector = selector => selector === '[data-open-quicknav]' ? trigger : (selector === 'dialog[open]' ? (confirmation.open ? confirmation : (quicknav.open ? quicknav : null)) : null);
    document.querySelectorAll = selector => selector.includes('.sidebar nav') ? originals : (selector === '.workspace-dialog' ? (fallback ? [quicknav] : [quicknav, confirmation]) : []);
    const window = new Element();
    window.confirm = () => allowFallback;
    vm.runInNewContext(source + '\n' + formsSource, {document, window});
    return {document, window, trigger, quicknav, search, results, count, empty, confirmation, action, cancel, title, message};
}

function submitEvent(form, submitter) {
    return {target: form, submitter, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }};
}

test('quick navigation only searches the links rendered for each role', () => {
    const roles = {admin: ['Dashboard', 'Rapat', 'Pengguna', 'Profil'], operator: ['Dashboard', 'Rapat', 'Klien', 'Profil'], user: ['Dashboard', 'Rapat', 'Tugas saya', 'Profil'], client: ['Dashboard', 'Rapat', 'Negosiasi jadwal', 'Profil']};
    for (const [role, labels] of Object.entries(roles)) {
        const state = setup(labels);
        state.trigger.click();
        assert.equal(state.quicknav.open, true);
        assert.equal(state.search.focused, true);
        assert.equal(state.results.children.length, labels.length);
        state.search.value = ' PENGGUNA ';
        state.search.emit('input');
        const visible = state.results.children.filter(link => !link.hidden);
        assert.equal(visible.length, role === 'admin' ? 1 : 0);
        assert.equal(state.empty.hidden, role === 'admin');
        state.search.emit('keydown', {key: 'Enter', preventDefault() {}});
        assert.equal(visible[0]?.clicked ?? 0, role === 'admin' ? 1 : 0);
        state.quicknav.close();
        assert.equal(state.trigger.focused, true);
    }
});

test('cancel never submits and confirmation preserves the submitter exactly once', () => {
    const state = setup();
    const submitter = new Element('BUTTON', 'Hapus akun');
    submitter.setAttribute('aria-label', 'Hapus akun Contoh');
    submitter.name = 'action';
    submitter.value = 'delete';
    const form = new Element('FORM');
    form.method = 'post';
    form.dataset = {confirm: 'Hapus akun ini?'};
    let submitted = 0;
    let lastSubmitter;
    let invalid = false;
    form.requestSubmit = button => {
        lastSubmitter = button;
        if (invalid) return;
        const event = submitEvent(form, button);
        state.document.emit('submit', event);
        if (!event.defaultPrevented) submitted++;
    };
    let event = submitEvent(form, submitter);
    state.document.emit('submit', event);
    assert.equal(event.defaultPrevented, true);
    assert.equal(form.getAttribute('aria-busy'), null);
    assert.equal(state.cancel.focused, true);
    assert.equal(state.title.textContent, 'Hapus akun Contoh');
    state.cancel.click();
    state.action.click();
    assert.equal(submitted, 0);
    event = submitEvent(form, submitter);
    state.document.emit('submit', event);
    state.action.click();
    state.action.click();
    assert.equal(submitted, 1);
    assert.equal(lastSubmitter, submitter);
    assert.equal(submitter.name, 'action');
    assert.equal(submitter.value, 'delete');
    assert.equal(form.getAttribute('aria-busy'), 'true');
    event = submitEvent(form, submitter);
    state.document.emit('submit', event);
    assert.equal(event.defaultPrevented, true);
    assert.equal(state.confirmation.open, false);
    state.window.emit('pageshow');
    assert.equal(form.getAttribute('aria-busy'), null);
    assert.equal(submitter.getAttribute('aria-disabled'), null);
    invalid = true;
    event = submitEvent(form, submitter);
    state.document.emit('submit', event);
    state.action.click();
    assert.equal(form.getAttribute('aria-busy'), null);
    invalid = false;
    event = submitEvent(form, submitter);
    state.document.emit('submit', event);
    assert.equal(event.defaultPrevented, true);
    assert.equal(state.confirmation.open, true);
});

test('unsupported dialogs still require native confirmation', () => {
    for (const allowFallback of [false, true]) {
        const state = setup([], {fallback: true, allowFallback});
        const form = new Element('FORM');
        form.method = 'post';
        form.dataset = {confirm: 'Hapus data ini?'};
        const event = submitEvent(form, new Element('BUTTON', 'Hapus'));
        state.document.emit('submit', event);
        assert.equal(event.defaultPrevented, !allowFallback);
        assert.equal(form.getAttribute('aria-busy'), allowFallback ? 'true' : null);
    }
});
