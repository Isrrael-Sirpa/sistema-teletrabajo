// The first click keeps the native download; only rapid repeated clicks are ignored.
document.addEventListener('DOMContentLoaded', () => {
    const status = document.querySelector('[data-report-export-status]');
    document.querySelectorAll('[data-report-export]').forEach((link) => {
        const label = link.querySelector('[data-report-export-label]');
        const originalLabel = label.textContent;
        let resetTimer;
        const reset = () => {
            window.clearTimeout(resetTimer);
            link.removeAttribute('aria-disabled');
            link.classList.remove('ft-pending-submit');
            label.textContent = originalLabel;
        };

        link.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            if (link.getAttribute('aria-disabled') === 'true') {
                event.preventDefault();
                return;
            }

            link.setAttribute('aria-disabled', 'true');
            link.classList.add('ft-pending-submit');
            label.textContent = 'Preparando archivo…';
            status.textContent = 'Descarga solicitada. El archivo aparecerá en las descargas de su navegador.';
            // This is a short click guard, not a claim that the server has finished.
            resetTimer = window.setTimeout(reset, 4000);
        });
        window.addEventListener('pageshow', reset);
    });
});
