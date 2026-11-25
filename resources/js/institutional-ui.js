// Mejoras progresivas: los formularios y enlaces siguen funcionando sin JavaScript.
export function mountSubmitFeedback(root, schedule = queueMicrotask) {
    const pending = new Map();
    root.addEventListener('submit', (event) => {
        const form = event.target;
        if (event.defaultPrevented || form.method?.toLowerCase() !== 'post' || form.target === '_blank') return;
        if (pending.has(form)) {
            event.preventDefault();
            return;
        }

        // Espera a los manejadores específicos (p. ej. destinatarios) antes de intervenir.
        schedule(() => {
            if (event.defaultPrevented || pending.has(form)) return;
            const button = event.submitter;
            pending.set(form, button ? { button, html: button.innerHTML, label: button.getAttribute('aria-label') } : null);
            form.setAttribute('aria-busy', 'true');
            const status = root.querySelector('[data-interaction-status]');
            if (status) status.textContent = 'Procesando solicitud. Espere un momento.';
            if (button) {
                // No deshabilitar: podría excluir name/value del envío nativo.
                button.setAttribute('aria-disabled', 'true');
                button.classList.add('ft-pending-submit');
                button.textContent = form.dataset.submitLabel || 'Procesando…';
            }
        });
    });

    return () => {
        pending.forEach((state, form) => {
            form.removeAttribute('aria-busy');
            if (! state) return;
            state.button.removeAttribute('aria-disabled');
            state.button.classList.remove('ft-pending-submit');
            state.button.innerHTML = state.html;
            if (state.label !== null) state.button.setAttribute('aria-label', state.label);
        });
        pending.clear();
        const status = root.querySelector('[data-interaction-status]');
        if (status) status.textContent = '';
    };
}

export function mountPasswordToggle(button, input) {
    button.hidden = false;
    button.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(reveal));
        const visibleEye = typeof button.querySelector === 'function'
            ? button.querySelector('[data-password-eye-visible]')
            : null;
        const hiddenEye = typeof button.querySelector === 'function'
            ? button.querySelector('[data-password-eye-hidden]')
            : null;
        visibleEye?.toggleAttribute('hidden', reveal);
        hiddenEye?.toggleAttribute('hidden', !reveal);
        button.setAttribute('aria-label', reveal ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
}

export function mountUserRoleUnits(form) {
    const role = form.querySelector('[data-role-select]');
    const unit = form.querySelector('[data-unit-select]');
    const section = form.querySelector('[data-unit-section]');
    const fixedUnit = form.querySelector('[data-fixed-unit]');
    const fixedUnitInput = form.querySelector('[data-fixed-unit-input]');
    if (! role || ! unit || ! section || ! fixedUnit || ! fixedUnitInput) return;

    const accepts = (option) => {
        if (! option.value) return true;
        if (role.value === 'administrator') return option.dataset.unitType === 'department' && option.dataset.unitAcronym === 'DCOMP';
        if (role.value === 'co_administrator') return ['dean_office', 'vice_dean_office'].includes(option.dataset.unitType);
        if (role.value === 'career_head') return ! ['faculty', 'dean_office', 'vice_dean_office'].includes(option.dataset.unitType);
        if (role.value === 'extra') return option.dataset.unitType === 'career';
        return role.value !== 'kardex_head' && option.dataset.unitType !== 'faculty';
    };
    const sync = () => {
        const hasRole = role.value !== '';
        const administrator = role.value === 'administrator';
        section.hidden = ! hasRole;
        fixedUnit.hidden = !administrator;
        unit.hidden = administrator;
        unit.disabled = !hasRole || administrator;
        unit.required = hasRole && !administrator;
        fixedUnitInput.disabled = !administrator;
        const available = [...unit.options].filter((option) => option.value && accepts(option));
        [...unit.options].forEach((option) => {
            option.hidden = ! accepts(option);
            option.disabled = ! accepts(option);
            if (option.dataset.defaultLabel) option.textContent = option.dataset.defaultLabel;
            if (role.value === 'co_administrator' && option.value) option.textContent = option.dataset.unitType === 'dean_office' ? 'Decanato' : 'Vicedecanato';
        });
        const prompt = unit.options[0];
        if (prompt) {
            prompt.textContent = {
                co_administrator: 'Seleccione una unidad',
                career_head: 'Seleccione la unidad',
                extra: 'Seleccione la carrera',
            }[role.value] || 'Seleccione la unidad';
        }
        if (unit.selectedOptions[0]?.disabled) unit.value = '';
        if (administrator && available.length === 1) {
            unit.value = available[0].value;
            fixedUnitInput.value = available[0].value;
        }
    };
    role.addEventListener('change', sync);
    sync();
}

export function mountSidebar(root, viewport) {
    const sidebar = root.querySelector('[data-sidebar]');
    const overlay = root.querySelector('[data-sidebar-overlay]');
    const opener = root.querySelector('[data-sidebar-open]');
    const closer = root.querySelector('[data-sidebar-close]');
    const content = root.querySelector('[data-workspace-content]');
    if (! sidebar || ! overlay || ! opener || ! closer || ! content) return;

    const isOpen = () => viewport.matches && opener.getAttribute('aria-expanded') === 'true';
    const sync = () => {
        sidebar.inert = viewport.matches && ! isOpen();
        sidebar.setAttribute('aria-hidden', String(sidebar.inert));
        content.inert = isOpen();
        if (isOpen()) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
        } else {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
        }
    };
    const close = (restoreFocus = true) => {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        root.body.classList.remove('overflow-hidden');
        opener.setAttribute('aria-expanded', 'false');
        sync();
        if (restoreFocus) opener.focus();
    };

    sync();
    opener.addEventListener('click', () => {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        root.body.classList.add('overflow-hidden');
        opener.setAttribute('aria-expanded', 'true');
        sync();
        closer.focus();
    });
    closer.addEventListener('click', () => close());
    overlay.addEventListener('click', () => close());
    viewport.addEventListener('change', () => close(false));
    root.addEventListener('keydown', (event) => {
        if (! isOpen()) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
        if (event.key !== 'Tab') return;
        const controls = [...sidebar.querySelectorAll('a[href], button:not([disabled]), summary')]
            .filter((element) => element.getClientRects().length > 0);
        const first = controls[0];
        const last = controls.at(-1);
        if ((event.shiftKey && root.activeElement === first) || (! event.shiftKey && root.activeElement === last)) {
            event.preventDefault();
            (event.shiftKey ? last : first)?.focus();
        }
    });
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', () => {
    const resetPending = mountSubmitFeedback(document);
    window.addEventListener('pageshow', (event) => {
        resetPending();
        // Revalidate authentication before using a private page restored by Back.
        if (event.persisted && document.querySelector('[data-workspace-content]')) window.location.reload();
    });
    mountSidebar(document, window.matchMedia('(max-width: 1023px)'));

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (input) mountPasswordToggle(button, input);
    });
    document.querySelectorAll('[data-user-form]').forEach(mountUserRoleUnits);

    const errors = document.querySelector('[data-form-errors]');
    if (errors) {
        errors.setAttribute('tabindex', '-1');
        errors.focus();
    }

    document.querySelectorAll('[data-profile-menu]').forEach((menu) => {
        document.addEventListener('click', (event) => {
            if (! menu.contains(event.target)) menu.open = false;
        });
        menu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menu.open) {
                menu.open = false;
                menu.querySelector('summary').focus();
            }
        });
        menu.addEventListener('focusout', (event) => {
            // Keep the menu open while focus moves to an action inside it.
            // A null destination can be a transient pointer-focus transition.
            if (event.relatedTarget && ! menu.contains(event.relatedTarget)) menu.open = false;
        });
    });
});
