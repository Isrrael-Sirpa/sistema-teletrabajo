import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-user-form]').forEach((form) => {
        const role = form.querySelector('[data-role-select]');
        const unitSection = form.querySelector('[data-unit-section]');
        const unitSelect = form.querySelector('[data-unit-select]');

        if (! role || ! unitSection || ! unitSelect) {
            return;
        }

        const globalRoles = ['administrator', 'co_administrator'];
        const updateUnitVisibility = () => {
            const requiresCareer = role.value !== '' && ! globalRoles.includes(role.value);

            unitSection.hidden = ! requiresCareer;
            unitSelect.required = requiresCareer;
            unitSelect.disabled = ! requiresCareer;

            if (! requiresCareer) {
                unitSelect.value = '';
            }
        };

        role.addEventListener('change', updateUnitVisibility);
        updateUnitVisibility();
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
