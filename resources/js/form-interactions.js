for (const [index, input] of [...document.querySelectorAll('input[type="password"]')].entries()) {
    if (!input.id) input.id = `password-field-${index + 1}`;
    const label = input.labels[0]?.textContent.trim().toLowerCase() || 'password';
    const wrapper = document.createElement('div');
    wrapper.className = 'password-field';
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'password-toggle';
    toggle.textContent = 'Lihat';
    toggle.setAttribute('aria-controls', input.id);
    toggle.setAttribute('aria-pressed', 'false');
    toggle.setAttribute('aria-label', `Tampilkan ${label}`);
    input.before(wrapper);
    wrapper.append(input, toggle);
    toggle.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        toggle.textContent = visible ? 'Sembunyikan' : 'Lihat';
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.setAttribute('aria-label', `${visible ? 'Sembunyikan' : 'Tampilkan'} ${label}`);
    });
}

for (const [index, grid] of [...document.querySelectorAll('.participant-grid')].entries()) {
    const participants = [...grid.querySelectorAll('label')].filter((label) => label.querySelector('input[type="checkbox"]'));
    if (!participants.length) continue;
    if (!grid.id) grid.id = `participant-options-${index + 1}`;
    const toolbar = document.createElement('div');
    toolbar.className = 'participant-toolbar';
    const label = document.createElement('label');
    label.htmlFor = `participant-search-${index + 1}`;
    label.textContent = 'Cari peserta';
    const search = document.createElement('input');
    search.type = 'search';
    search.id = label.htmlFor;
    search.className = 'participant-search';
    search.placeholder = 'Nama atau peran peserta';
    search.setAttribute('aria-controls', grid.id);
    const count = document.createElement('p');
    count.className = 'participant-count';
    count.setAttribute('aria-live', 'polite');
    count.setAttribute('aria-atomic', 'true');
    const empty = document.createElement('p');
    empty.className = 'participant-empty';
    empty.textContent = 'Tidak ada peserta yang cocok. Coba nama atau peran lain.';
    empty.setAttribute('role', 'status');
    toolbar.append(label, search, count);
    grid.before(toolbar);
    grid.after(empty);
    const updateCount = () => {
        count.textContent = `${grid.querySelectorAll('input[type="checkbox"]:checked').length} peserta dipilih`;
    };
    const filterParticipants = () => {
        const query = search.value.trim().toLocaleLowerCase('id');
        for (const participant of participants) {
            participant.hidden = !participant.textContent.toLocaleLowerCase('id').includes(query);
        }
        empty.hidden = participants.some((participant) => !participant.hidden);
    };
    search.addEventListener('input', filterParticipants);
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') event.preventDefault();
    });
    grid.addEventListener('change', updateCount);
    grid.closest('form')?.addEventListener('reset', () => {
        requestAnimationFrame(() => {
            updateCount();
            filterParticipants();
        });
    });
    updateCount();
    filterParticipants();
}

const pendingForms = new Map();
document.addEventListener('submit', (event) => {
    const form = event.target;
    const method = event.submitter?.getAttribute('formmethod') || form.method;
    if (event.defaultPrevented || ['get', 'dialog'].includes(method.toLowerCase()) || form.closest('[data-meeting-chat]')) return;
    if (pendingForms.has(form)) {
        event.preventDefault();
        return;
    }

    const button = event.submitter || form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
    const state = {busy: form.getAttribute('aria-busy'), button, disabled: button?.getAttribute('aria-disabled')};
    form.setAttribute('aria-busy', 'true');
    if (button) {
        button.setAttribute('aria-disabled', 'true');
        if (button.tagName === 'BUTTON') {
            state.content = [...button.childNodes];
            button.textContent = 'Memproses…';
        } else {
            state.status = document.createElement('span');
            state.status.className = 'form-submit-status';
            state.status.setAttribute('role', 'status');
            state.status.textContent = 'Memproses…';
            button.after(state.status);
        }
    }
    pendingForms.set(form, state);
});

window.addEventListener('pageshow', () => {
    for (const [form, state] of pendingForms) {
        if (state.busy === null) form.removeAttribute('aria-busy');
        else form.setAttribute('aria-busy', state.busy);
        if (state.button) {
            if (state.disabled === null) state.button.removeAttribute('aria-disabled');
            else state.button.setAttribute('aria-disabled', state.disabled);
            if (state.content) state.button.replaceChildren(...state.content);
            state.status?.remove();
        }
    }
    pendingForms.clear();
});

