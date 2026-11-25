import './bootstrap';
import './multi-career-recipients';
import './document-file-viewer';
import './institutional-ui';
import './report-export';

document.addEventListener('DOMContentLoaded', () => {
    const returnAnchor = sessionStorage.getItem('ft-return-anchor');
    if (returnAnchor) {
        sessionStorage.removeItem('ft-return-anchor');
        document.getElementById(returnAnchor)?.scrollIntoView({ block: 'start' });
    }

    document.querySelectorAll('form[data-return-anchor]').forEach((form) => {
        form.addEventListener('submit', () => sessionStorage.setItem('ft-return-anchor', form.dataset.returnAnchor));
    });

    document.querySelectorAll('form[data-confirm-title]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                delete form.dataset.confirmed;
                return;
            }

            event.preventDefault();
            const dialog = document.createElement('dialog');
            dialog.className = 'fixed inset-0 m-auto w-[min(92vw,30rem)] rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-950/50';
            const content = document.createElement('div');
            content.className = 'p-6';
            const title = document.createElement('h2');
            title.className = 'text-xl font-bold text-slate-950';
            title.textContent = form.dataset.confirmTitle;
            const message = document.createElement('p');
            message.className = 'mt-3 text-sm leading-6 text-slate-700';
            message.textContent = form.dataset.confirmMessage;
            const actions = document.createElement('div');
            actions.className = 'mt-6 flex flex-wrap justify-end gap-3';
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'ft-button-secondary';
            cancel.textContent = 'Cancelar';
            const confirm = document.createElement('button');
            confirm.type = 'button';
            confirm.className = 'ft-button-primary';
            confirm.textContent = form.dataset.confirmAction || event.submitter?.textContent?.trim() || 'Confirmar';
            cancel.addEventListener('click', () => dialog.close());
            confirm.addEventListener('click', () => {
                form.dataset.confirmed = 'true';
                dialog.close();
                form.requestSubmit(event.submitter);
            });
            dialog.addEventListener('close', () => dialog.remove());
            actions.append(cancel, confirm);
            content.append(title, message, actions);
            dialog.append(content);
            document.body.append(dialog);
            dialog.showModal();
        });
    });

    document.querySelectorAll('a[href^="#"]').forEach((link) => {
        const target = document.getElementById(link.hash.slice(1));
        const details = target?.closest('details');

        if (details) {
            link.addEventListener('click', () => { details.open = true; });
        }
    });

    document.querySelectorAll('[data-personal-recipient-select]').forEach((userSelect) => {
        const form = userSelect.closest('form');
        const purposeSelect = form?.querySelector('[data-personal-recipient-purpose]');
        const help = form?.querySelector('[data-personal-recipient-help]');

        if (! purposeSelect || ! help) {
            return;
        }

        const formalOption = purposeSelect.querySelector('option[value="1"]');
        const updatePurpose = () => {
            const selected = userSelect.options[userSelect.selectedIndex];
            const canRequireResponse = selected?.dataset.canRequireResponse !== '0';

            formalOption.disabled = ! canRequireResponse;
            help.hidden = canRequireResponse;

            if (! canRequireResponse) {
                purposeSelect.value = '0';
            }
        };

        userSelect.addEventListener('change', updatePurpose);
        updatePurpose();
    });
});
