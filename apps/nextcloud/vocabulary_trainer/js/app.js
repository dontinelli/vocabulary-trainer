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
            const title = makeElement('h3', '', collection.name);
            const languages = makeElement('p', 'vt-card-languages', `${collection.sourceLanguage.toUpperCase()} → ${collection.targetLanguage.toUpperCase()}`);
            const meta = makeElement('div', 'vt-card-meta');
            const entryBadge = makeElement('span', 'vt-badge', `${collection.entryCount} ${strings.entries}`);
            const boxBadge = makeElement('span', 'vt-badge', `${collection.boxCount} ${strings.boxCount.toLowerCase()}`);
            const actions = makeElement('div', 'vt-card-actions');

            meta.append(entryBadge, boxBadge);
            actions.append(
                makeAction(strings.edit, 'edit', collection.id),
                makeAction(strings.remove, 'remove', collection.id),
            );
            card.append(title, languages, meta, actions);
            list.append(card);
        });
    }

    function openEditor(collection) {
        editingId = collection ? collection.id : null;
        form.reset();
        errorMessage.hidden = true;
        errorMessage.textContent = '';
        document.getElementById('collection-dialog-title').textContent = collection
            ? strings.editCollection
            : strings.addCollection;
        form.elements.name.value = collection ? collection.name : '';
        form.elements.sourceLanguage.value = collection ? collection.sourceLanguage : '';
        form.elements.targetLanguage.value = collection ? collection.targetLanguage : '';
        form.elements.boxCount.value = collection ? collection.boxCount : 5;
        editDialog.showModal();
        form.elements.name.focus();
    }

    document.getElementById('add-collection').addEventListener('click', () => openEditor(null));

    document.querySelectorAll('[data-close]').forEach((closeButton) => {
        closeButton.addEventListener('click', () => {
            document.getElementById(closeButton.dataset.close).close();
        });
    });
    document.querySelectorAll('dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });

    root.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-action="add"]');
        if (addButton) {
            openEditor(null);
            return;
        }

        const actionButton = event.target.closest('[data-action="edit"], [data-action="remove"]');
        if (!actionButton) {
            return;
        }

        const collection = collections.find((item) => item.id === Number(actionButton.dataset.id));
        if (!collection) {
            return;
        }

        if (actionButton.dataset.action === 'edit') {
            openEditor(collection);
        } else {
            removingId = collection.id;
            removeError.hidden = true;
            removeError.textContent = '';
            removePrompt.textContent = strings.removePrompt.replace('{name}', collection.name);
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
        try {
            await request(`/${encodeURIComponent(removingId)}`, 'DELETE');
            removingId = null;
            removeDialog.close();
            if (await loadCollections()) {
                announce(strings.collectionRemoved);
            }
        } catch (error) {
            removeError.textContent = error.message;
            removeError.hidden = false;
        }
    });

    render();
    loadCollections();
}());
