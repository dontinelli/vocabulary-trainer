(function () {
    'use strict';

    const root = document.getElementById('vocabulary-trainer');
    if (!root) {
        return;
    }

    const strings = JSON.parse(root.dataset.strings);

    const collections = [
        { id: 1, name: 'English – German basics', sourceLanguage: 'en', targetLanguage: 'de', boxCount: 5, entryCount: 24 },
        { id: 2, name: 'French – Spanish practice', sourceLanguage: 'fr', targetLanguage: 'es', boxCount: 3, entryCount: 12 },
    ];
    let nextId = 3;
    let editingId = null;
    let removingId = null;

    const list = document.getElementById('collection-list');
    const emptyState = document.getElementById('collection-empty');
    const editDialog = document.getElementById('collection-dialog');
    const removeDialog = document.getElementById('remove-dialog');
    const form = document.getElementById('collection-form');
    const errorMessage = document.getElementById('collection-form-error');
    const announcement = document.getElementById('collection-announcement');
    const removePrompt = document.getElementById('remove-prompt');

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
        emptyState.hidden = collections.length > 0;

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

    root.addEventListener('click', (event) => {
        const closeButton = event.target.closest('[data-close]');
        if (closeButton) {
            document.getElementById(closeButton.dataset.close).close();
            return;
        }

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
            removePrompt.textContent = strings.removePrompt.replace('{name}', collection.name);
            removeDialog.showModal();
        }
    });

    form.addEventListener('submit', (event) => {
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

        if (editingId === null) {
            collections.push({ id: nextId++, ...values, entryCount: 0 });
            announce(strings.collectionCreated);
        } else {
            const index = collections.findIndex((collection) => collection.id === editingId);
            if (index >= 0) {
                collections[index] = { ...collections[index], ...values };
            }
            announce(strings.collectionUpdated);
        }

        editDialog.close();
        render();
    });

    document.getElementById('confirm-remove').addEventListener('click', () => {
        const index = collections.findIndex((collection) => collection.id === removingId);
        if (index >= 0) {
            collections.splice(index, 1);
            render();
            announce(strings.collectionRemoved);
        }
        removingId = null;
        removeDialog.close();
    });

    render();
}());
