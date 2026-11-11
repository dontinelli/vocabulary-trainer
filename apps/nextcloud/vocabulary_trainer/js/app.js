(function () {
    'use strict';

    const root = document.getElementById('vocabulary-trainer');
    if (!root) {
        return;
    }

    const strings = JSON.parse(root.dataset.strings);

    const collections = [];
    const collectionsUrl = OC.generateUrl('/apps/vocabulary_trainer/collections');
    let collectionsLoaded = false;
    let collectionsLoadFailed = false;
    let editingId = null;
    let removingId = null;
    let activeCollection = null;
    let editingEntryId = null;
    const entries = [];
    const selectedEntryIds = new Set();

    const list = document.getElementById('collection-list');
    const emptyState = document.getElementById('collection-empty');
    const editDialog = document.getElementById('collection-dialog');
    const removeDialog = document.getElementById('remove-dialog');
    const form = document.getElementById('collection-form');
    const errorMessage = document.getElementById('collection-form-error');
    const loadError = document.getElementById('collection-load-error');
    const removeError = document.getElementById('remove-error');
    const announcement = document.getElementById('collection-announcement');
    const removePrompt = document.getElementById('remove-prompt');
    const overview = document.getElementById('overview-content');
    const detail = document.getElementById('collection-detail');
    const entryList = document.getElementById('entry-list');
    const entryEmpty = document.getElementById('entry-empty');
    const entryLoadError = document.getElementById('entry-load-error');
    const entryDialog = document.getElementById('entry-dialog');
    const entryForm = document.getElementById('entry-form');
    const entryFormError = document.getElementById('entry-form-error');
    const bulkDeleteDialog = document.getElementById('bulk-delete-dialog');
    const bulkDeleteError = document.getElementById('bulk-delete-error');
    const targetCollection = document.getElementById('target-collection');

    async function request(path, method, data) {
        const headers = { requesttoken: OC.requestToken };
        const options = { method, headers };
        if (data) {
            headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
            options.body = new URLSearchParams(data).toString();
        }

        let response;
        try {
            response = await fetch(`${collectionsUrl}${path}`, options);
        } catch {
            throw new Error(strings.networkError);
        }

        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch {
            result = null;
        }
        if (!response.ok) {
            if (typeof result?.error === 'string') {
                throw new Error(result.error);
            }
            if (response.status === 404) {
                throw new Error(strings.apiNotFound.replace('{status}', response.status));
            }
            throw new Error(strings.httpError.replace('{status}', response.status));
        }
        if (result === null) {
            throw new Error(strings.invalidResponse.replace('{status}', response.status));
        }
        return result;
    }

    async function loadCollections() {
        try {
            const result = await request('', 'GET');
            if (!Array.isArray(result)) {
                throw new Error(strings.invalidResponse.replace('{status}', 200));
            }
            collections.splice(0, collections.length, ...result);
            collectionsLoadFailed = false;
            loadError.hidden = true;
            collectionsLoaded = true;
            render();
            if (activeCollection) {
                renderMoveTargets();
            }
            return true;
        } catch (error) {
            collectionsLoadFailed = true;
            loadError.textContent = error.message;
            loadError.hidden = false;
            collectionsLoaded = true;
            render();
            return false;
        }
    }

    function announce(message) {
        announcement.textContent = message;
    }

    function makeElement(tag, className, text) {
        const element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        if (text !== undefined) {
            element.textContent = text;
        }
        return element;
    }

    function makeAction(label, action, id) {
        const button = makeElement('button', 'vt-button', label);
        button.type = 'button';
        button.dataset.action = action;
        button.dataset.id = String(id);
        return button;
    }

    function render() {
        list.replaceChildren();
        emptyState.hidden = !collectionsLoaded || collectionsLoadFailed || collections.length > 0;

        collections.forEach((collection) => {
            const card = makeElement('article', 'vt-collection-card');
            const title = makeElement('h3');
            const titleLink = makeElement('a', 'vt-collection-title', collection.name);
            titleLink.href = `#collection-${encodeURIComponent(collection.id)}`;
            titleLink.dataset.action = 'open';
            titleLink.dataset.id = String(collection.id);
            title.append(titleLink);
            const languages = makeElement('p', 'vt-card-languages', `${collection.sourceLanguage.toUpperCase()} → ${collection.targetLanguage.toUpperCase()}`);
            const meta = makeElement('div', 'vt-card-meta');
            const entryBadge = makeElement('span', 'vt-badge', `${collection.entryCount} ${strings.entries}`);
            const boxBadge = makeElement('span', 'vt-badge', `${collection.boxCount} ${strings.boxCount.toLowerCase()}`);
            const actions = makeElement('div', 'vt-card-actions');

            meta.append(entryBadge, boxBadge);
            actions.append(
                makeAction(strings.openCollection, 'open', collection.id),
                makeAction(strings.edit, 'edit', collection.id),
                makeAction(strings.remove, 'remove', collection.id),
            );
            card.append(title, languages, meta, actions);
            list.append(card);
        });
    }

    function renderEntries() {
        entryList.replaceChildren();
        entryEmpty.hidden = entries.length > 0;
        document.getElementById('collection-entry-count').textContent = String(entries.length);
        const selectedCount = selectedEntryIds.size;
        document.getElementById('selected-count').textContent =
            strings.selectedCount.replace('{count}', selectedCount);
        document.getElementById('delete-selected').disabled = selectedCount === 0;

        const selectAll = document.getElementById('select-all-entries');
        selectAll.checked = entries.length > 0 && selectedCount === entries.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < entries.length;

        entries.forEach((entry) => {
            const item = makeElement('li', 'vt-entry-item');
            const checkboxLabel = makeElement('label', 'vt-entry-select');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = String(entry.id);
            checkbox.checked = selectedEntryIds.has(entry.id);
            checkbox.setAttribute('aria-label', `${strings.selectEntry}: ${entry.sourceTerm}`);
            checkboxLabel.append(checkbox);

            const terms = makeElement('div', 'vt-entry-terms');
            terms.append(
                makeElement('div', 'vt-entry-term vt-entry-source', entry.sourceTerm),
                makeElement('div', 'vt-entry-term', entry.targetTerm),
            );
            if (entry.notes) {
                terms.append(makeElement('p', 'vt-entry-notes', entry.notes));
            }

            const actions = makeElement('div', 'vt-entry-actions');
            actions.append(makeAction(strings.edit, 'edit-entry', entry.id));
            item.append(checkboxLabel, terms, actions);
            entryList.append(item);
        });

        renderMoveTargets();
    }

    function renderMoveTargets() {
        targetCollection.replaceChildren();
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = strings.chooseCollection;
        targetCollection.append(placeholder);

        collections
            .filter((collection) => collection.id !== activeCollection?.id)
            .forEach((collection) => {
                const option = document.createElement('option');
                option.value = String(collection.id);
                option.textContent = collection.name;
                targetCollection.append(option);
            });
        document.getElementById('move-selected').disabled =
            selectedEntryIds.size === 0 || targetCollection.options.length < 2;
    }

    async function openCollection(collection) {
        activeCollection = collection;
        selectedEntryIds.clear();
        document.getElementById('detail-heading').textContent = collection.name;
        document.getElementById('detail-languages').textContent =
            `${collection.sourceLanguage.toUpperCase()} → ${collection.targetLanguage.toUpperCase()}`;
        document.getElementById('collection-entry-count').textContent = String(collection.entryCount);
        document.getElementById('collection-box-count').textContent = String(collection.boxCount);
        overview.hidden = true;
        detail.hidden = false;
        await loadEntries();
    }

    async function loadEntries() {
        if (!activeCollection) {
            return false;
        }
        try {
            const result = await request(`/${encodeURIComponent(activeCollection.id)}/entries`, 'GET');
            if (!Array.isArray(result)) {
                throw new Error(strings.invalidResponse.replace('{status}', 200));
            }
            entries.splice(0, entries.length, ...result);
            selectedEntryIds.clear();
            entryLoadError.hidden = true;
            renderEntries();
            return true;
        } catch (error) {
            entryLoadError.textContent = `${strings.entriesLoadFailed} ${error.message}`;
            entryLoadError.hidden = false;
            entries.splice(0);
            renderEntries();
            return false;
        }
    }

    function openEntryEditor(entry = null) {
        editingEntryId = entry ? entry.id : null;
        entryForm.reset();
        document.getElementById('save-entry-add-another').hidden = entry !== null;
        entryFormError.hidden = true;
        entryFormError.textContent = '';
        document.getElementById('entry-dialog-title').textContent =
            entry ? strings.editEntry : strings.addEntry;
        entryForm.elements.sourceTerm.value = entry ? entry.sourceTerm : '';
        entryForm.elements.targetTerm.value = entry ? entry.targetTerm : '';
        entryForm.elements.notes.value = entry ? (entry.notes || '') : '';
        entryDialog.hidden = false;
        entryDialog.showModal();
        entryForm.elements.sourceTerm.focus();
    }

    function openEditor(collection) {
        editingId = collection ? collection.id : null;
        form.reset();
        errorMessage.textContent = collectionsLoadFailed ? loadError.textContent : '';
        errorMessage.hidden = !collectionsLoadFailed;
        document.getElementById('collection-dialog-title').textContent = collection
            ? strings.editCollection
            : strings.addCollection;
        form.elements.name.value = collection ? collection.name : '';
        form.elements.sourceLanguage.value = collection ? collection.sourceLanguage : '';
        form.elements.targetLanguage.value = collection ? collection.targetLanguage : '';
        form.elements.boxCount.value = collection ? collection.boxCount : 5;
        editDialog.hidden = false;
        editDialog.showModal();
        form.elements.name.focus();
    }

    function closeDialog(dialog) {
        if (dialog.open) {
            dialog.close();
        }
        dialog.hidden = true;
    }

    document.getElementById('add-collection').addEventListener('click', () => openEditor(null));

    document.querySelectorAll('[data-close]').forEach((closeButton) => {
        closeButton.addEventListener('click', () => {
            closeDialog(document.getElementById(closeButton.dataset.close));
        });
    });
    document.querySelectorAll('dialog').forEach((dialog) => {
        dialog.addEventListener('close', () => {
            dialog.hidden = true;
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                closeDialog(dialog);
            }
        });
    });

    root.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-action="add"]');
        if (addButton) {
            openEditor(null);
            return;
        }

        const actionButton = event.target.closest('[data-action="open"], [data-action="edit"], [data-action="remove"]');
        if (!actionButton) {
            return;
        }
        if (actionButton.matches('a')) {
            event.preventDefault();
        }

        const collectionId = Number(actionButton.dataset.id);
        if (!Number.isSafeInteger(collectionId) || collectionId < 1) {
            return;
        }
        const collection = collections.find((item) => item.id === collectionId);
        if (!collection) {
            return;
        }

        if (actionButton.dataset.action === 'open') {
            openCollection(collection);
        } else if (actionButton.dataset.action === 'edit') {
            openEditor(collection);
        } else {
            removingId = collection.id;
            removeError.hidden = true;
            removeError.textContent = '';
            removePrompt.textContent = strings.removePrompt.replace('{name}', collection.name);
            removeDialog.hidden = false;
            removeDialog.showModal();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) {
            return;
        }

        const sourceLanguage = form.elements.sourceLanguage.value.trim();
        const targetLanguage = form.elements.targetLanguage.value.trim();

        if (sourceLanguage.toLowerCase() === targetLanguage.toLowerCase()) {
            errorMessage.textContent = strings.languageError;
            errorMessage.hidden = false;
            form.elements.targetLanguage.focus();
            return;
        }

        const values = {
            name: form.elements.name.value.trim(),
            sourceLanguage,
            targetLanguage,
            boxCount: Number(form.elements.boxCount.value),
        };

        const method = editingId === null ? 'POST' : 'PUT';
        const path = editingId === null ? '' : `/${encodeURIComponent(editingId)}`;
        try {
            await request(path, method, values);
            editDialog.close();
            if (await loadCollections()) {
                announce(editingId === null ? strings.collectionCreated : strings.collectionUpdated);
            }
        } catch (error) {
            errorMessage.textContent = error.message;
            errorMessage.hidden = false;
        }
    });

    document.getElementById('confirm-remove').addEventListener('click', async () => {
        if (!Number.isSafeInteger(removingId) || removingId < 1) {
            removeError.textContent = strings.requestFailed;
            removeError.hidden = false;
            return;
        }
        try {
            await request(`/${encodeURIComponent(removingId)}`, 'DELETE');
            removingId = null;
            closeDialog(removeDialog);
            if (await loadCollections()) {
                announce(strings.collectionRemoved);
            }
        } catch (error) {
            removeError.textContent = error.message;
            removeError.hidden = false;
        }
    });

    document.getElementById('back-to-collections').addEventListener('click', () => {
        detail.hidden = true;
        overview.hidden = false;
        activeCollection = null;
        selectedEntryIds.clear();
    });

    document.getElementById('add-entry').addEventListener('click', () => openEntryEditor());

    entryList.addEventListener('change', (event) => {
        if (!event.target.matches('input[type="checkbox"]')) {
            return;
        }
        const id = Number(event.target.value);
        if (event.target.checked) {
            selectedEntryIds.add(id);
        } else {
            selectedEntryIds.delete(id);
        }
        renderEntries();
    });

    document.getElementById('select-all-entries').addEventListener('change', (event) => {
        selectedEntryIds.clear();
        if (event.target.checked) {
            entries.forEach((entry) => selectedEntryIds.add(entry.id));
        }
        renderEntries();
    });

    entryList.addEventListener('click', (event) => {
        const editButton = event.target.closest('[data-action="edit-entry"]');
        if (!editButton) {
            return;
        }
        const entry = entries.find((item) => item.id === Number(editButton.dataset.id));
        if (entry) {
            openEntryEditor(entry);
        }
    });

    entryForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!entryForm.reportValidity() || !activeCollection) {
            return;
        }
        const values = {
            sourceTerm: entryForm.elements.sourceTerm.value.trim(),
            targetTerm: entryForm.elements.targetTerm.value.trim(),
            notes: entryForm.elements.notes.value.trim(),
        };
        if (!values.sourceTerm || !values.targetTerm) {
            entryFormError.textContent = strings.entryNameRequired;
            entryFormError.hidden = false;
            return;
        }

        const wasNewEntry = editingEntryId === null;
        const saveAndAddAnother = wasNewEntry && event.submitter?.id === 'save-entry-add-another';
        const method = wasNewEntry ? 'POST' : 'PUT';
        const path = `/${encodeURIComponent(activeCollection.id)}/entries` +
            (wasNewEntry ? '' : `/${encodeURIComponent(editingEntryId)}`);
        try {
            await request(path, method, values);
            if (saveAndAddAnother) {
                entryForm.elements.sourceTerm.value = '';
                entryForm.elements.targetTerm.value = '';
                entryForm.elements.notes.value = '';
                entryForm.elements.sourceTerm.focus();
            } else {
                closeDialog(entryDialog);
            }
            await loadEntries();
            await loadCollections();
            announce(wasNewEntry ? strings.entrySaved : strings.entryUpdated);
        } catch (error) {
            entryFormError.textContent = error.message;
            entryFormError.hidden = false;
        }
    });

    document.getElementById('move-selected').addEventListener('click', async () => {
        const ids = [...selectedEntryIds];
        const targetId = Number(targetCollection.value);
        if (ids.length === 0 || !Number.isSafeInteger(targetId) || targetId < 1 || !activeCollection) {
            entryLoadError.textContent = strings.chooseCollection;
            entryLoadError.hidden = false;
            return;
        }
        try {
            await request(`/${encodeURIComponent(activeCollection.id)}/entries/move`, 'POST', {
                entryIds: JSON.stringify(ids),
                targetCollectionId: String(targetId),
            });
            if (await loadEntries()) {
                await loadCollections();
                announce(strings.entriesMoved);
            }
        } catch (error) {
            entryLoadError.textContent = error.message;
            entryLoadError.hidden = false;
        }
    });

    document.getElementById('delete-selected').addEventListener('click', () => {
        const count = selectedEntryIds.size;
        if (count === 0) {
            return;
        }
        document.getElementById('bulk-delete-prompt').textContent =
            strings.confirmDeleteEntries.replace('{count}', count);
        bulkDeleteError.hidden = true;
        bulkDeleteError.textContent = '';
        bulkDeleteDialog.hidden = false;
        bulkDeleteDialog.showModal();
    });

    document.getElementById('confirm-delete-selected').addEventListener('click', async () => {
        const ids = [...selectedEntryIds];
        if (ids.length === 0 || !activeCollection) {
            bulkDeleteError.textContent = strings.selectEntriesError;
            bulkDeleteError.hidden = false;
            return;
        }
        try {
            await request(`/${encodeURIComponent(activeCollection.id)}/entries`, 'DELETE', {
                entryIds: JSON.stringify(ids),
            });
            closeDialog(bulkDeleteDialog);
            if (await loadEntries()) {
                await loadCollections();
                announce(strings.entriesDeleted);
            }
        } catch (error) {
            bulkDeleteError.textContent = error.message;
            bulkDeleteError.hidden = false;
        }
    });

    render();
    loadCollections();
}());
