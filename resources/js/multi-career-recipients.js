document.addEventListener('DOMContentLoaded', () => {
    const editor = document.querySelector('[data-recipient-editor]');
    const form = editor?.querySelector('[data-multi-career-form]');
    if (! form) return;

    const search = form.querySelector('[data-career-search]');
    const count = form.querySelector('[data-career-selection-count]');
    const submit = form.querySelector('[data-career-submit]');
    const feedback = editor.querySelector('[data-recipient-feedback]');
    const retry = editor.querySelector('[data-recipient-sync]');
    const versionInput = form.querySelector('[data-multi-career-lock-version]');
    const fragments = ['[data-recipient-list]', '[data-recipient-counts]', '[data-career-options]', '[data-copy-unit-options]', '[data-copy-person-options]', '[data-recipient-send-summary]', '[data-recipient-send-controls]'];
    const disabledBefore = new Map();
    const choices = () => [...form.querySelectorAll('[data-career-choice]')];
    const selectable = (choice) => ! (disabledBefore.get(choice) ?? choice.disabled);
    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    let busy = false;
    let blocked = false;
    let feedbackTimer;

    const updateCount = () => {
        const selected = choices().filter((choice) => choice.checked && selectable(choice)).length;
        count.textContent = `${selected} ${selected === 1 ? 'unidad seleccionada' : 'unidades seleccionadas'}.`;
        submit.disabled = busy || blocked || selected === 0;
    };
    const filter = () => {
        const term = normalize(search.value.trim());
        choices().forEach((choice) => {
            const option = choice.closest('[data-career-option]');
            option.hidden = term !== '' && ! normalize(option.textContent).includes(term);
        });
    };
    const showMessage = (message, error = false) => {
        clearTimeout(feedbackTimer);
        feedback.hidden = false;
        feedback.setAttribute('role', error ? 'alert' : 'status');
        feedback.textContent = message;
        if (! error && ! busy) feedbackTimer = setTimeout(() => { feedback.hidden = true; }, 6000);
    };
    const lockControls = () => {
        const controls = [
            ...editor.querySelectorAll('form button, form select, form input:not([type="hidden"])'),
            ...document.querySelectorAll('[data-recipient-send-controls] button'),
        ];
        controls.forEach((control) => {
            if (! disabledBefore.has(control)) disabledBefore.set(control, control.disabled);
            control.disabled = true;
        });
        editor.setAttribute('aria-busy', String(busy));
    };
    const finish = () => {
        busy = false;
        disabledBefore.forEach((disabled, control) => { control.disabled = disabled; });
        disabledBefore.clear();
        editor.setAttribute('aria-busy', 'false');
        submit.textContent = 'Agregar unidades seleccionadas';
        if (blocked) lockControls();
        updateCount();
    };

    // Replace only named fragments of the same authorized draft, never the full page.
    const applyPage = (page) => {
        const freshEditor = page.querySelector('[data-recipient-editor]');
        const rawVersion = freshEditor?.querySelector('[data-multi-career-lock-version]')?.value;
        const version = Number(rawVersion);
        if (freshEditor?.dataset.recipientEditor !== editor.dataset.recipientEditor
            || rawVersion === undefined || ! Number.isInteger(version) || version < 0) return false;

        const replacements = fragments.map((selector) => [document.querySelector(selector), page.querySelector(selector)]);
        if (replacements.some(([current, fresh]) => current && ! fresh)) return false;
        const selected = new Set(choices().filter((choice) => choice.checked && selectable(choice)).map((choice) => choice.value));
        replacements.forEach(([current, fresh]) => {
            if (current && fresh) current.replaceChildren(...[...fresh.childNodes].map((node) => node.cloneNode(true)));
        });
        choices().forEach((choice) => { if (! choice.disabled) choice.checked = selected.has(choice.value); });
        document.querySelectorAll('input[name="lock_version"], input[name="document_lock_version"]')
            .forEach((input) => { input.value = String(version); });
        if (busy || blocked) lockControls();
        filter();
        updateCount();
        return true;
    };
    const parse = async (response) => new DOMParser().parseFromString(await response.text(), 'text/html');
    const synchronize = async () => {
        const response = await fetch(window.location.href.split('#')[0], {
            credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'text/html' },
        });
        if (! response.ok || ! applyPage(await parse(response))) throw new Error('No fue posible actualizar los destinatarios. Reintente la actualización.');
        blocked = false;
        retry.hidden = true;
    };
    const mutate = async (targetForm, data) => {
        const expectedVersion = Number(versionInput.value);
        data.set('lock_version', String(expectedVersion));
        const response = await fetch(targetForm.action, {
            method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'text/html' },
        });
        const page = await parse(response);
        const explanation = page.querySelector('[role="alert"]')?.textContent?.trim();
        const applied = applyPage(page);
        if (! applied) {
            blocked = true;
            const error = new Error('No fue posible confirmar la operación con el sistema.');
            error.confirmedFailure = ! response.ok;
            throw error;
        }
        if (! response.ok || Number(versionInput.value) !== expectedVersion + 1 || explanation) {
            const error = new Error(explanation?.replace(/Recargue la página[^.]*\.?/gi, '').trim()
                || 'El borrador cambió o el sistema rechazó la operación.');
            error.confirmedFailure = Boolean(explanation) || ! response.ok;
            throw error;
        }
    };
    const recover = async () => {
        try {
            await synchronize();
            return '';
        } catch {
            blocked = true;
            retry.hidden = false;
            return ' No se pudo confirmar el estado actual con el servidor. Use Reintentar actualización.';
        }
    };

    search.addEventListener('input', filter);
    form.addEventListener('change', updateCount);
    // Delegation keeps Quitar/Actualizar working on rows returned by subsequent requests.
    document.addEventListener('submit', async (event) => {
        const target = event.target;
        const multiple = target === form;
        if (event.defaultPrevented || (! multiple && ! (editor.contains(target) && target.matches('[data-recipient-operation]')))) return;
        event.preventDefault();
        if (busy || blocked) return;

        const selected = [...new Map(choices().filter((choice) => choice.checked && selectable(choice))
            .map((choice) => [choice.value, { id: choice.value, name: choice.dataset.careerName }])).values()];
        if (multiple && selected.length === 0) return;
        const data = new FormData(target);
        let completed = 0;
        let success = false;
        let message;
        busy = true;
        lockControls();
        if (multiple) submit.textContent = 'Agregando...';
        showMessage(multiple ? 'Agregando unidades…' : 'Guardando destinatarios…');

        try {
            if (multiple) {
                for (const choice of selected) {
                    data.set('recipient_type', 'unit');
                    data.set('unit_id', choice.id);
                    data.set('requires_response', '1');
                    await mutate(form, data);
                    completed += 1;
                    showMessage(`${completed} de ${selected.length} unidades agregadas…`);
                }
                message = selected.length === 1
                    ? `✓ ${selected[0].name} se agregó correctamente.` : `✓ ${selected.length} unidades agregadas correctamente.`;
            } else {
                await mutate(target, data);
                message = { remove: '✓ Destinatario quitado correctamente.', update: '✓ Destinatario actualizado correctamente.', 'add-copy': '✓ Copia informativa agregada correctamente.' }[target.dataset.recipientOperation];
                if (target.dataset.recipientOperation === 'add-copy') {
                    target.querySelectorAll('select').forEach((select) => { select.value = ''; });
                }
            }
            success = true;
        } catch (error) {
            const recovery = await recover();
            message = multiple
                ? `Se agregaron ${completed} de ${selected.length} unidades. ${selected[completed].name} ${error.confirmedFailure ? 'no pudo agregarse' : 'no pudo confirmarse'}. ${error.message} Revise los destinatarios antes de enviar.${recovery}`
                : `No se pudo completar la operación. ${error.message} Revise los destinatarios antes de enviar.${recovery}`;
        } finally {
            finish();
            showMessage(message, ! success);
            feedback.scrollIntoView({ block: 'nearest' });
        }
    });
    retry.addEventListener('click', async () => {
        if (busy) return;
        busy = true;
        lockControls();
        try {
            await synchronize();
            finish();
            showMessage('Destinatarios actualizados. Revise la selección antes de continuar.');
        } catch (error) {
            finish();
            showMessage(error.message, true);
        }
    });
    updateCount();
});
