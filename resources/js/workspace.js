const quicknav = document.getElementById('quicknav-dialog');
const quicknavTrigger = document.querySelector('[data-open-quicknav]');

if (quicknav && quicknavTrigger && typeof quicknav.showModal === 'function') {
    const search = quicknav.querySelector('input');
    const results = quicknav.querySelector('[data-quicknav-results]');
    const count = quicknav.querySelector('[data-quicknav-count]');
    const empty = quicknav.querySelector('[data-quicknav-empty]');
    const sources = document.querySelectorAll('.sidebar nav a[href], .account-popup a[href]');
    let opener;
    const links = [...sources].map((source) => {
        const link = document.createElement('a');
        link.href = source.href;
        const icon = source.querySelector('svg')?.cloneNode(true);
        if (icon) link.append(icon);
        const label = document.createElement('span');
        label.textContent = source.textContent.trim().replace(/\s+/g, ' ').replace(/\s*↗$/, '');
        link.append(label);
        if (source.hasAttribute('aria-current')) {
            link.setAttribute('aria-current', 'page');
            const current = document.createElement('small');
            current.textContent = 'Halaman ini';
            link.append(current);
        }
        results.append(link);
        return {link, label: label.textContent.toLocaleLowerCase('id-ID')};
    });
    const filter = () => {
        const query = search.value.trim().toLocaleLowerCase('id-ID');
        let visible = 0;
        for (const item of links) {
            item.link.hidden = !item.label.includes(query);
            if (!item.link.hidden) visible++;
        }
        empty.hidden = visible > 0;
        count.textContent = visible + ' halaman tersedia';
    };
    const open = () => {
        if (document.querySelector('dialog[open]')) return;
        opener = document.activeElement;
        search.value = '';
        filter();
        quicknav.showModal();
        search.focus();
    };
    quicknavTrigger.hidden = false;
    quicknavTrigger.addEventListener('click', open);
    search.addEventListener('input', filter);
    search.addEventListener('keydown', (event) => {
        if (event.isComposing) return;
        const firstLink = links.find((item) => !item.link.hidden)?.link;
        if (firstLink && ['Enter', 'ArrowDown'].includes(event.key)) {
            event.preventDefault();
            if (event.key === 'Enter') firstLink.click();
            else firstLink.focus();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (!event.defaultPrevented && !event.isComposing && !event.altKey && (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            open();
        }
    });
    quicknav.addEventListener('close', () => opener?.isConnected && opener.focus());
}

const confirmation = document.getElementById('confirm-dialog');
const approvedForms = new WeakSet();
let pendingConfirmation;

document.addEventListener('submit', (event) => {
    const form = event.target;
    const message = form.dataset.confirm;
    if (event.defaultPrevented || !message || approvedForms.delete(form) || form.getAttribute('aria-busy') === 'true') return;
    if (!confirmation || typeof confirmation.showModal !== 'function' || typeof form.requestSubmit !== 'function') {
        if (!window.confirm(message)) event.preventDefault();
        return;
    }
    event.preventDefault();
    if (confirmation.open) return;
    const submitter = event.submitter;
    pendingConfirmation = {form, submitter};
    confirmation.querySelector('[data-confirm-title]').textContent = submitter?.getAttribute('aria-label') || submitter?.textContent.trim() || 'Konfirmasi tindakan';
    confirmation.querySelector('[data-confirm-message]').textContent = message;
    confirmation.querySelector('[data-confirm-action]').textContent = submitter?.textContent.trim() || 'Lanjutkan';
    confirmation.showModal();
    confirmation.querySelector('[data-close-dialog]').focus();
});

if (confirmation) {
    confirmation.querySelector('[data-confirm-action]').addEventListener('click', () => {
        if (!confirmation.open || !pendingConfirmation) return;
        const {form, submitter} = pendingConfirmation;
        pendingConfirmation = null;
        confirmation.close();
        approvedForms.add(form);
        try {
            if (submitter) form.requestSubmit(submitter);
            else form.requestSubmit();
        } finally {
            approvedForms.delete(form);
        }
    });
    confirmation.addEventListener('close', () => { if (!confirmation.open) pendingConfirmation = null; });
}

for (const dialog of document.querySelectorAll('.workspace-dialog')) {
    for (const button of dialog.querySelectorAll('[data-close-dialog]')) {
        button.addEventListener('click', () => dialog.close());
    }
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
}

for (const notice of document.querySelectorAll('.alert.success')) {
    const message = document.createElement('span');
    message.append(...notice.childNodes);
    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.className = 'notice-dismiss';
    dismiss.setAttribute('aria-label', 'Tutup notifikasi sukses');
    dismiss.textContent = '×';
    dismiss.addEventListener('click', () => {
        const heading = notice.parentElement.querySelector('h1');
        if (heading) {
            heading.tabIndex = -1;
            heading.focus({preventScroll: true});
        }
        notice.remove();
    });
    notice.append(message, dismiss);
}


