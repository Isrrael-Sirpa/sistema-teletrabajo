document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('[data-file-viewer]');

    if (! dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    const items = [...dialog.querySelectorAll('[data-file-viewer-item]')];
    const name = dialog.querySelector('[data-file-viewer-name]');
    const count = dialog.querySelector('[data-file-viewer-count]');
    const status = dialog.querySelector('[data-file-viewer-status]');
    const content = dialog.querySelector('[data-file-viewer-content]');
    const download = dialog.querySelector('[data-file-viewer-download]');
    const previous = dialog.querySelector('[data-file-viewer-previous]');
    const next = dialog.querySelector('[data-file-viewer-next]');
    let selected = 0;
    let opener;
    let controller;
    let objectUrl;
    let bodyOverflow;
    let generation = 0;

    const clearPreview = () => {
        generation += 1;
        controller?.abort();
        content.replaceChildren();
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = undefined;
        }
    };

    const select = async (index) => {
        clearPreview();
        selected = index;
        const file = items[index].dataset;
        const requestGeneration = generation;
        name.textContent = file.fileName;
        count.textContent = `Archivo ${index + 1} de ${items.length}`;
        download.href = file.fileDownloadUrl;
        previous.disabled = index === 0;
        next.disabled = index === items.length - 1;
        items.forEach((item, position) => {
            if (position === index) {
                item.setAttribute('aria-current', 'true');
            } else {
                item.removeAttribute('aria-current');
            }
        });

        if (file.fileSupported !== '1') {
            status.textContent = 'Este archivo no puede visualizarse directamente en el sistema. Puede utilizar Descargar archivo.';
            return;
        }

        status.textContent = 'Cargando archivo…';
        controller = new AbortController();

        try {
            const response = await fetch(file.filePreviewUrl, {
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
                headers: { Accept: file.fileMime },
            });
            if (! response.ok) {
                const messages = {
                    403: 'No tiene autorización para obtener este archivo.',
                    404: 'El archivo no está disponible. Informe al responsable del sistema.',
                    429: 'Se realizaron demasiadas solicitudes. Intente nuevamente en un momento.',
                };
                throw new Error(messages[response.status] || 'No fue posible visualizar el archivo. Vuelva al documento e intente nuevamente.');
            }
            if (response.headers.get('Content-Type')?.split(';')[0].trim() !== file.fileMime) {
                throw new Error('La sesión venció o el archivo no está disponible. Vuelva al documento para continuar.');
            }

            const blob = await response.blob();
            if (generation !== requestGeneration || ! dialog.open) {
                return;
            }
            objectUrl = URL.createObjectURL(blob);
            if (file.fileMime === 'application/pdf') {
                const frame = document.createElement('iframe');
                frame.title = `Visualización de ${file.fileName}`;
                frame.src = objectUrl;
                content.append(frame);
                status.textContent = 'Si su navegador no muestra el PDF, puede utilizar Descargar archivo.';
            } else {
                const image = document.createElement('img');
                image.alt = file.fileName;
                image.src = objectUrl;
                image.addEventListener('error', () => {
                    if (generation === requestGeneration) {
                        status.textContent = 'No fue posible mostrar la imagen. Puede utilizar Descargar archivo.';
                    }
                });
                content.append(image);
                status.textContent = '';
            }
        } catch (error) {
            if (error.name !== 'AbortError' && generation === requestGeneration) {
                status.textContent = error instanceof TypeError
                    ? 'No fue posible conectar con el sistema. Intente nuevamente.' : error.message;
            }
        }
    };

    document.querySelectorAll('[data-file-viewer-open]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const index = items.findIndex((item) => item.dataset.fileId === link.dataset.fileViewerOpen);
            if (index < 0) {
                return;
            }
            event.preventDefault();
            opener = link;
            bodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            dialog.showModal();
            select(index);
        });
    });
    items.forEach((item, index) => item.addEventListener('click', () => { select(index); }));
    previous.addEventListener('click', () => { if (selected > 0) select(selected - 1); });
    next.addEventListener('click', () => { if (selected < items.length - 1) select(selected + 1); });
    dialog.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') {
            return;
        }
        const focusable = [...dialog.querySelectorAll('button:not([disabled]), a[href], iframe')]
            .filter((element) => element.getClientRects().length > 0);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
        } else if (! event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
        }
    });
    dialog.querySelector('[data-file-viewer-close]').addEventListener('click', () => { dialog.close(); });
    dialog.addEventListener('close', () => {
        clearPreview();
        document.body.style.overflow = bodyOverflow;
        opener?.focus();
    });
});
