import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('[data-sidebar]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const openButton = document.querySelector('[data-sidebar-open]');
    const closeButton = document.querySelector('[data-sidebar-close]');

    if (sidebar && overlay && openButton && closeButton) {
        const mobileViewport = window.matchMedia('(max-width: 1023px)');
        const syncSidebarAccessibility = () => {
            const closedOnMobile = mobileViewport.matches && openButton.getAttribute('aria-expanded') !== 'true';
            sidebar.inert = closedOnMobile;
            sidebar.setAttribute('aria-hidden', closedOnMobile ? 'true' : 'false');
        };
        const closeSidebar = () => {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            openButton.setAttribute('aria-expanded', 'false');
            syncSidebarAccessibility();
            openButton.focus();
        };

        syncSidebarAccessibility();
        mobileViewport.addEventListener('change', () => {
            if (! mobileViewport.matches && openButton.getAttribute('aria-expanded') === 'true') {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                openButton.setAttribute('aria-expanded', 'false');
            }

            syncSidebarAccessibility();
        });
        openButton.addEventListener('click', () => {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            openButton.setAttribute('aria-expanded', 'true');
            syncSidebarAccessibility();
            closeButton.focus();
        });
        closeButton.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && openButton.getAttribute('aria-expanded') === 'true') {
                closeSidebar();
            }
        });
    }

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
