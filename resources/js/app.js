import './chat';
import './workspace';
import './form-interactions';

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

const revealContent = (element) => {
    element.getAnimations?.().forEach((animation) => animation.cancel());
    if (reducedMotion.matches || !element.animate) return;

    element.animate([
        {opacity: 0, transform: 'translateY(-4px)'},
        {opacity: 1, transform: 'translateY(0)'},
    ], {duration: 160, easing: 'cubic-bezier(0.2, 0, 0, 1)'});
};

for (const details of document.querySelectorAll('.account-menu, .filter-options, .edit-details')) {
    let wasOpen = details.open;
    details.addEventListener('toggle', () => {
        const focusedField = details.contains(document.activeElement) && document.activeElement.tagName !== 'SUMMARY';
        if (details.open && !wasOpen && !focusedField) {
            for (const content of details.children) {
                if (content.tagName !== 'SUMMARY') revealContent(content);
            }
        }
        wasOpen = details.open;
    });
}



const accountMenu = document.querySelector('.account-menu');

if (accountMenu) {
    document.addEventListener('click', (event) => {
        if (!accountMenu.contains(event.target)) {
            accountMenu.open = false;
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && accountMenu.open && !document.querySelector('dialog[open]')) {
            accountMenu.open = false;
            accountMenu.querySelector('summary').focus();
        }
    });
}

const menuToggle = document.querySelector('.menu-toggle');
const sidebarMenu = document.getElementById('sidebar-menu');

if (menuToggle && sidebarMenu) {
    const mobile = window.matchMedia('(max-width: 650px)');

    const setMenuOpen = (open) => {
        const opening = mobile.matches && sidebarMenu.hidden && open;
        const restoreFocus = sidebarMenu.contains(document.activeElement);
        sidebarMenu.hidden = mobile.matches && !open;
        menuToggle.hidden = !mobile.matches;
        menuToggle.setAttribute('aria-expanded', String(mobile.matches && open));
        menuToggle.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        if (opening) revealContent(sidebarMenu);
        if (sidebarMenu.hidden && restoreFocus) {
            menuToggle.focus();
        }
    };

    menuToggle.addEventListener('click', () => setMenuOpen(sidebarMenu.hidden));
    mobile.addEventListener('change', () => setMenuOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobile.matches && !sidebarMenu.hidden && !document.querySelector('dialog[open]')) {
            setMenuOpen(false);
            menuToggle.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (mobile.matches && !sidebarMenu.hidden && !event.target.closest('.sidebar')) {
            setMenuOpen(false);
        }
    });
    sidebarMenu.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            setMenuOpen(false);
        }
    });
    setMenuOpen(false);
}

const accountRole = document.getElementById('role');
const clientAccount = document.querySelector('[data-client-account]');
if (accountRole && clientAccount) {
    const updateClientAccount = () => {
        const isClient = accountRole.value === 'client';
        clientAccount.hidden = !isClient;
        clientAccount.querySelector('select').disabled = !isClient;
        clientAccount.querySelector('select').required = isClient;
    };
    accountRole.addEventListener('change', updateClientAccount);
    updateClientAccount();
}


const forms = [...document.forms];
const mutationForms = forms.filter((form) => form.method.toLowerCase() !== 'get');
for (const form of mutationForms) {
    const method = form.querySelector('[name="_method"]')?.value || form.method.toUpperCase();
    const identity = `${new URL(form.action, location.href).pathname}:${method}`;
    form.dataset.validationForm = identity;
    if (!form.querySelector('[name="_form"]')) {
        const identityInput = document.createElement('input');
        identityInput.type = 'hidden';
        identityInput.name = '_form';
        identityInput.value = identity;
        form.append(identityInput);
    }
}

const validationSummary = document.querySelector('[data-validation-summary]');
if (validationSummary) {
    const submittedForm = mutationForms.find((form) => form.dataset.validationForm === validationSummary.dataset.submittedForm);
    for (const [index, item] of [...validationSummary.querySelectorAll('[data-error-field]')].entries()) {
        const fieldName = item.dataset.errorField;
        const candidates = submittedForm ? [submittedForm] : (validationSummary.dataset.submittedForm ? [] : forms);
        const matches = candidates.map((form) => [...form.elements].filter((field) => {
            if (!field.name || field.type === 'hidden' || field.disabled) return false;
            const name = field.name.replace(/\[([^\]]*)\]/g, '.$1').replace(/\.$/, '');
            return name === fieldName || fieldName.startsWith(`${name}.`);
        })).filter((fields) => fields.length);
        if (matches.length !== 1) continue;

        const fields = matches[0];
        const firstField = fields[0];
        if (!firstField.id) firstField.id = `validation-field-${index}`;
        const inlineError = document.createElement('p');
        inlineError.id = `validation-error-${index}`;
        inlineError.className = 'field-error';
        inlineError.textContent = item.textContent;
        const group = fields.length > 1 ? firstField.closest('.participant-grid, fieldset') : null;
        (group || firstField).after(inlineError);
        for (const field of fields) {
            field.setAttribute('aria-invalid', 'true');
            const descriptions = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            field.setAttribute('aria-describedby', [...new Set([...descriptions, inlineError.id])].join(' '));
        }

        const link = document.createElement('a');
        link.href = `#${firstField.id}`;
        link.textContent = item.textContent;
        link.addEventListener('click', (event) => {
            event.preventDefault();
            let details = firstField.closest('details');
            while (details) {
                details.open = true;
                details = details.parentElement.closest('details');
            }
            firstField.focus();
            firstField.scrollIntoView({block: 'center'});
        });
        item.replaceChildren(link);
    }
    requestAnimationFrame(() => validationSummary.focus());
}



