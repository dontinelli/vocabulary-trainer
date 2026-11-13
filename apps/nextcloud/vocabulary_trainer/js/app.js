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
    let collectionStatistics = null;
    let entryFilter = null;
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
    const studySetupDialog = document.getElementById('study-setup-dialog');
    const studySetupForm = document.getElementById('study-setup-form');
    const studySetupError = document.getElementById('study-setup-error');
    const responseTimeInput = studySetupForm.elements.questionTimeLimitSeconds;
    const studyView = document.getElementById('study-view');
    const studyQuestionView = document.getElementById('study-question-view');
    const studyAnswerForm = document.getElementById('study-answer-form');
    const studyTimerControls = document.getElementById('study-timer-controls');
    const studyTimerProgress = document.getElementById('study-auto-advance');
    const pauseStudyTimerButton = document.getElementById('pause-study-timer');
    let studySession = null;
    let studyIndex = 0;
    let studyCorrect = 0;
    let studyAnswered = false;
    let studyAdvanceFrame = null;
    let studyTimerTotalMs = 0;
    let studyTimerRemainingMs = 0;
    let studyTimerLastFrame = null;
    let studyTimerPaused = false;
    let studyTimerPhase = null;
    let studySubmitting = false;
    let saveAndAddAnotherRequested = false;

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
        const visibleEntries = entryFilter
            ? entries.filter((entry) => entry.boxes[entryFilter.direction] === entryFilter.box)
            : entries;
        entryEmpty.hidden = visibleEntries.length > 0;
        document.getElementById('entry-empty-message').textContent =
            entries.length === 0 ? strings.noEntries : strings.noPairsInBox;
        document.getElementById('collection-entry-count').textContent = String(entries.length);
        const selectedCount = visibleEntries.filter((entry) => selectedEntryIds.has(entry.id)).length;
        document.getElementById('selected-count').textContent =
            strings.selectedCount.replace('{count}', selectedCount);
        document.getElementById('delete-selected').disabled = selectedCount === 0;

        const selectAll = document.getElementById('select-all-entries');
        selectAll.checked = visibleEntries.length > 0 && selectedCount === visibleEntries.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < visibleEntries.length;

        visibleEntries.forEach((entry) => {
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

    function renderCollectionStatistics() {
        const distribution = document.getElementById('box-distribution');
        distribution.replaceChildren();
        if (!collectionStatistics) {
            document.getElementById('collection-box-count').textContent = '—';
            document.getElementById('collection-accuracy').textContent = '—';
            document.getElementById('collection-learning-progress').textContent = '—';
            return;
        }

        document.getElementById('collection-box-count').textContent = String(collectionStatistics.boxCount);
        document.getElementById('collection-accuracy').textContent =
            collectionStatistics.accuracyPercent === null
                ? '—'
                : `${Math.round(collectionStatistics.accuracyPercent)}%`;
        document.getElementById('collection-learning-progress').textContent =
            collectionStatistics.learningProgressPercent === null
                ? '—'
                : `${Math.round(collectionStatistics.learningProgressPercent)}%`;

        const allPairs = makeElement('button', `vt-button${entryFilter ? '' : ' is-active'}`, strings.allPairs);
        allPairs.type = 'button';
        allPairs.dataset.action = 'filter-box';
        allPairs.setAttribute('aria-pressed', String(entryFilter === null));
        distribution.append(allPairs);

        [
            ['source_to_target', strings.sourceToTarget],
            ['target_to_source', strings.targetToSource],
        ].forEach(([direction, label]) => {
            const group = makeElement('div', 'vt-box-distribution-group');
            group.append(makeElement('h4', '', label));
            const buttons = makeElement('div', 'vt-box-distribution-buttons');
            collectionStatistics.boxCounts[direction].forEach((count, index) => {
                const box = index + 1;
                const isActive = entryFilter?.direction === direction && entryFilter.box === box;
                const button = makeElement(
                    'button',
                    `vt-button${isActive ? ' is-active' : ''}`,
                    `${strings.boxNumber} ${box} (${count})`,
                );
                button.type = 'button';
                button.dataset.action = 'filter-box';
                button.dataset.direction = direction;
                button.dataset.box = String(box);
                button.setAttribute('aria-pressed', String(isActive));
                buttons.append(button);
            });
            group.append(buttons);
            distribution.append(group);
        });
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
        entryFilter = null;
        collectionStatistics = null;
        renderCollectionStatistics();
        studyView.hidden = true;
        detail.hidden = false;
        document.getElementById('detail-heading').textContent = collection.name;
        document.getElementById('detail-languages').textContent =
            `${collection.sourceLanguage.toUpperCase()} → ${collection.targetLanguage.toUpperCase()}`;
        document.getElementById('collection-entry-count').textContent = String(collection.entryCount);
        document.getElementById('collection-box-count').textContent = String(collection.boxCount);
        overview.hidden = true;
        detail.hidden = false;
        await loadEntries();
    }

    function updateStudySelectionOptions() {
        const selection = studySetupForm.elements.selection.value;
        document.getElementById('study-box-option').hidden = selection !== 'box';
        document.getElementById('study-error-rate-option').hidden = selection !== 'error-rate';
        document.getElementById('study-days-option').hidden = selection !== 'not-practiced';
    }

    async function openStudySetup() {
        if (!activeCollection) {
            return;
        }
        studySetupError.hidden = true;
        studySetupError.textContent = '';
        studySetupForm.elements.startWithNew.checked = true;
        studySetupForm.elements.includeFinalBox.checked = false;
        studySetupForm.elements.answerDelaySeconds.value = '5';
        studySetupForm.elements.limitResponseTime.checked = false;
        responseTimeInput.value = '30';
        responseTimeInput.disabled = true;
        const startButton = document.getElementById('start-study-session');
        startButton.disabled = true;
        const source = `${activeCollection.sourceLanguage.toUpperCase()} → ${activeCollection.targetLanguage.toUpperCase()}`;
        const target = `${activeCollection.targetLanguage.toUpperCase()} → ${activeCollection.sourceLanguage.toUpperCase()}`;
        studySetupForm.elements.direction.options[0].textContent = source;
        studySetupForm.elements.direction.options[1].textContent = target;

        const boxSelect = studySetupForm.elements.box;
        boxSelect.replaceChildren();
        for (let box = 1; box <= activeCollection.boxCount; box++) {
            const option = document.createElement('option');
            option.value = String(box);
            option.textContent = `${strings.boxNumber} ${box}`;
            boxSelect.append(option);
        }

        updateStudySelectionOptions();
        studySetupDialog.hidden = false;
        studySetupDialog.showModal();

        try {
            const settings = await request(`/${encodeURIComponent(activeCollection.id)}/study/settings`, 'GET');
            studySetupForm.elements.startWithNew.checked = Boolean(settings.startWithNew);
            studySetupForm.elements.includeFinalBox.checked = Boolean(settings.includeFinalBox);
            studySetupForm.elements.answerDelaySeconds.value = String(settings.answerDelaySeconds ?? 5);
            const responseTimeLimit = Number(settings.questionTimeLimitSeconds ?? 0);
            studySetupForm.elements.limitResponseTime.checked = responseTimeLimit > 0;
            responseTimeInput.value = String(responseTimeLimit > 0 ? responseTimeLimit : 30);
            responseTimeInput.disabled = !studySetupForm.elements.limitResponseTime.checked;
        } catch (error) {
            studySetupError.textContent = error.message;
            studySetupError.hidden = false;
        } finally {
            startButton.disabled = false;
        }
    }

    async function beginStudy(event) {
        event.preventDefault();
        if (!activeCollection || !studySetupForm.reportValidity()) {
            return;
        }
        const data = {
            direction: studySetupForm.elements.direction.value,
            mode: studySetupForm.elements.mode.value,
            selection: studySetupForm.elements.selection.value,
            box: studySetupForm.elements.box.value,
            errorRate: studySetupForm.elements.errorRate.value,
            days: studySetupForm.elements.days.value,
            startWithNew: studySetupForm.elements.startWithNew.checked ? '1' : '0',
            includeFinalBox: studySetupForm.elements.includeFinalBox.checked ? '1' : '0',
            answerDelaySeconds: studySetupForm.elements.answerDelaySeconds.value,
            questionTimeLimitSeconds: studySetupForm.elements.limitResponseTime.checked
                ? responseTimeInput.value
                : '0',
        };
        try {
            const session = await request(`/${encodeURIComponent(activeCollection.id)}/study/session`, 'POST', data);
            if (!Array.isArray(session.questions)) {
                throw new Error(strings.invalidResponse.replace('{status}', 201));
            }
            studySession = session;
            studyIndex = 0;
            studyCorrect = 0;
            studyAnswered = false;
            studySubmitting = false;
            closeDialog(studySetupDialog);
            detail.hidden = true;
            studyView.hidden = false;
            document.getElementById('study-language-direction').textContent =
                data.direction === 'source_to_target'
                    ? `${activeCollection.sourceLanguage.toUpperCase()} → ${activeCollection.targetLanguage.toUpperCase()}`
                    : `${activeCollection.targetLanguage.toUpperCase()} → ${activeCollection.sourceLanguage.toUpperCase()}`;
            document.getElementById('study-empty').hidden = session.questions.length > 0;
            document.getElementById('study-complete').hidden = true;
            studyQuestionView.hidden = session.questions.length === 0;
            if (session.questions.length > 0) {
                renderStudyQuestion();
            }
        } catch (error) {
            studySetupError.textContent = error.message;
            studySetupError.hidden = false;
        }
    }

    function renderStudyQuestion() {
        const question = studySession.questions[studyIndex];
        stopStudyAdvanceTimer();
        studyAnswered = false;
        studySubmitting = false;
        studyTimerPhase = 'question';
        studyTimerPaused = false;
        studyTimerControls.hidden = true;
        studyTimerProgress.hidden = true;
        studyTimerProgress.value = 100;
        pauseStudyTimerButton.hidden = true;
        updateStudyTimerButton();
        studyAnswerForm.classList.remove('is-correct', 'is-incorrect');
        document.getElementById('study-question-progress').textContent =
            strings.sessionProgress
                .replace('{current}', studyIndex + 1)
                .replace('{total}', studySession.questions.length);
        document.getElementById('study-prompt').textContent = question.prompt;
        document.getElementById('study-answer-area').replaceChildren();
        document.getElementById('study-answer-error').hidden = true;
        document.getElementById('study-feedback').hidden = true;
        document.getElementById('submit-study-answer').hidden = false;

        const answerArea = document.getElementById('study-answer-area');
        if (question.type === 'multiple-choice') {
            const choices = makeElement('div', 'vt-study-choices');
            question.choices.forEach((choice) => {
                const label = makeElement('label', 'vt-study-choice');
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'answer';
                radio.value = choice;
                label.append(radio, makeElement('span', '', choice));
                choices.append(label);
            });
            answerArea.append(choices);
            choices.querySelector('input')?.focus();
        } else {
            const answer = document.createElement('textarea');
            answer.name = 'answer';
            answer.rows = 2;
            answer.autocomplete = 'off';
            answer.setAttribute('aria-label', strings.typedMode);
            answerArea.append(answer);
            answer.focus();
        }
        if (studySession.questionTimeLimitSeconds > 0) {
            startStudyAdvanceTimer('question', studySession.questionTimeLimitSeconds);
        }
    }

    function stopStudyAdvanceTimer() {
        if (studyAdvanceFrame !== null) {
            cancelAnimationFrame(studyAdvanceFrame);
            studyAdvanceFrame = null;
        }
        studyTimerLastFrame = null;
    }

    function updateStudyTimerButton() {
        const label = studyTimerPaused ? strings.resumeAutomaticAdvance : strings.pauseAutomaticAdvance;
        pauseStudyTimerButton.setAttribute('aria-pressed', String(studyTimerPaused));
        pauseStudyTimerButton.setAttribute('aria-label', label);
        pauseStudyTimerButton.title = label;
    }

    function updateStudyAdvanceTimer(timestamp) {
        if (studyTimerPaused || studyTimerPhase === null || studySubmitting) {
            studyAdvanceFrame = null;
            return;
        }
        if (studyTimerLastFrame !== null) {
            studyTimerRemainingMs -= timestamp - studyTimerLastFrame;
        }
        studyTimerLastFrame = timestamp;
        studyTimerProgress.value = Math.max(0, studyTimerRemainingMs / studyTimerTotalMs * 100);
        if (studyTimerRemainingMs <= 0) {
            studyAdvanceFrame = null;
            const phase = studyTimerPhase;
            studyTimerPhase = null;
            if (phase === 'question') {
                void submitStudyAnswer(null, true);
            } else {
                advanceStudy();
            }
            return;
        }
        studyAdvanceFrame = requestAnimationFrame(updateStudyAdvanceTimer);
    }

    function startStudyAdvanceTimer(phase, durationSeconds) {
        stopStudyAdvanceTimer();
        studyTimerPhase = phase;
        studyTimerTotalMs = durationSeconds * 1000;
        studyTimerRemainingMs = studyTimerTotalMs;
        studyTimerPaused = false;
        studyTimerControls.hidden = phase !== 'answer';
        studyTimerProgress.value = 100;
        studyTimerProgress.hidden = false;
        studyTimerProgress.setAttribute(
            'aria-label',
            phase === 'question' ? strings.responseTimerLabel : strings.nextQuestionTimer,
        );
        pauseStudyTimerButton.hidden = phase !== 'answer';
        updateStudyTimerButton();
        studyAdvanceFrame = requestAnimationFrame(updateStudyAdvanceTimer);
    }

    function toggleStudyTimer() {
        if (studyTimerPhase === null) {
            return;
        }
        studyTimerPaused = !studyTimerPaused;
        updateStudyTimerButton();
        if (studyTimerPaused) {
            if (studyAdvanceFrame !== null) {
                cancelAnimationFrame(studyAdvanceFrame);
                studyAdvanceFrame = null;
            }
            studyTimerLastFrame = null;
            return;
        }
        studyTimerLastFrame = null;
        studyAdvanceFrame = requestAnimationFrame(updateStudyAdvanceTimer);
    }

    async function submitStudyAnswer(event, bypassValidation = false) {
        event?.preventDefault();
        if (!studySession || studyAnswered || studySubmitting || !activeCollection) {
            return;
        }
        if (!bypassValidation && !studyAnswerForm.reportValidity()) {
            return;
        }
        const question = studySession.questions[studyIndex];
        const answerValue = new FormData(studyAnswerForm).get('answer');
        const answer = typeof answerValue === 'string' ? answerValue : '';

        const submitButton = document.getElementById('submit-study-answer');
        studySubmitting = true;
        studyTimerPhase = null;
        stopStudyAdvanceTimer();
        submitButton.disabled = true;
        try {
            const result = await request(
                `/${encodeURIComponent(activeCollection.id)}/study/entries/${encodeURIComponent(question.entryId)}/answer`,
                'POST',
                { direction: studySession.direction, answer },
            );
            studyAnswered = true;
            if (result.correct) {
                studyCorrect++;
                studyAnswerForm.classList.add('is-correct');
                studyAnswerForm.classList.remove('is-incorrect');
                document.getElementById('study-feedback').textContent = strings.correctAnswer;
            } else {
                studyAnswerForm.classList.add('is-incorrect');
                studyAnswerForm.classList.remove('is-correct');
                document.getElementById('study-feedback').textContent =
                    strings.incorrectAnswer.replace('{answer}', () => result.correctAnswer);
            }
            document.getElementById('study-feedback').hidden = false;
            submitButton.hidden = true;
            studyAnswerForm.querySelectorAll('input, textarea').forEach((input) => {
                input.disabled = true;
            });
            startStudyAdvanceTimer('answer', studySession.answerDelaySeconds);
            document.getElementById('study-feedback').focus();
        } catch (error) {
            const message = document.getElementById('study-answer-error');
            message.textContent = error.message;
            message.hidden = false;
            studyTimerPhase = 'question';
            if (studySession.questionTimeLimitSeconds > 0) {
                startStudyAdvanceTimer('question', studySession.questionTimeLimitSeconds);
            }
        } finally {
            studySubmitting = false;
            submitButton.disabled = false;
        }
    }

    function advanceStudy() {
        if (!studySession || !studyAnswered) {
            return;
        }
        stopStudyAdvanceTimer();
        studyTimerPhase = null;
        studyTimerControls.hidden = true;
        studyTimerProgress.hidden = true;
        studyIndex++;
        if (studyIndex >= studySession.questions.length) {
            studyQuestionView.hidden = true;
            document.getElementById('study-complete').hidden = false;
            document.getElementById('study-summary').textContent =
                strings.sessionSummary
                    .replace('{correct}', studyCorrect)
                    .replace('{total}', studySession.questions.length);
            return;
        }
        renderStudyQuestion();
    }

    function exitStudy() {
        stopStudyAdvanceTimer();
        studyTimerPhase = null;
        studyView.hidden = true;
        detail.hidden = false;
        studySession = null;
    }

    async function loadEntries() {
        if (!activeCollection) {
            return false;
        }
        try {
            const collectionPath = `/${encodeURIComponent(activeCollection.id)}`;
            const [result, statistics] = await Promise.all([
                request(`${collectionPath}/entries`, 'GET'),
                request(`${collectionPath}/statistics`, 'GET'),
            ]);
            if (!Array.isArray(result) || !statistics || typeof statistics !== 'object') {
                throw new Error(strings.invalidResponse.replace('{status}', 200));
            }
            collectionStatistics = statistics;
            entries.splice(0, entries.length, ...result.map((entry) => ({
                ...entry,
                boxes: statistics.entryProgress[String(entry.id)] || {
                    source_to_target: 1,
                    target_to_source: 1,
                },
            })));
            selectedEntryIds.clear();
            renderCollectionStatistics();
            entryLoadError.hidden = true;
            renderEntries();
            return true;
        } catch (error) {
            entryLoadError.textContent = `${strings.entriesLoadFailed} ${error.message}`;
            entryLoadError.hidden = false;
            entries.splice(0);
            collectionStatistics = null;
            renderCollectionStatistics();
            renderEntries();
            return false;
        }
    }

    function openEntryEditor(entry = null) {
        editingEntryId = entry ? entry.id : null;
        saveAndAddAnotherRequested = false;
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
        document.getElementById('save-entry-add-another').addEventListener('click', () => {
            saveAndAddAnotherRequested = true;
        });
        entryForm.addEventListener('click', (event) => {
            if (event.target.closest('button[type="submit"]:not(#save-entry-add-another)')) {
                saveAndAddAnotherRequested = false;
            }
        });
        entryForm.addEventListener('invalid', () => {
            saveAndAddAnotherRequested = false;
        }, true);
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

    document.getElementById('start-learning').addEventListener('click', openStudySetup);
    studySetupForm.elements.selection.addEventListener('change', updateStudySelectionOptions);
    studySetupForm.elements.limitResponseTime.addEventListener('change', () => {
        responseTimeInput.disabled = !studySetupForm.elements.limitResponseTime.checked;
    });
    studySetupForm.addEventListener('submit', beginStudy);
    studyAnswerForm.addEventListener('submit', submitStudyAnswer);
    studyAnswerForm.addEventListener('change', (event) => {
        if (event.target.matches('input[type="radio"]')) {
            void submitStudyAnswer(null, true);
        }
    });
    pauseStudyTimerButton.addEventListener('click', toggleStudyTimer);
    document.querySelector('.vt-study-card').addEventListener('click', (event) => {
        if (event.target.closest('#pause-study-timer') || studySubmitting) {
            return;
        }
        if (studyTimerPhase === 'question') {
            if (event.target.closest('button, input, textarea, select, label, a')) {
                return;
            }
            void submitStudyAnswer(null, true);
        } else if (studyTimerPhase === 'answer') {
            advanceStudy();
        }
    });
    document.getElementById('study-feedback').addEventListener('keydown', (event) => {
        if ((event.key === 'Enter' || event.key === ' ') && studyTimerPhase === 'answer') {
            event.preventDefault();
            advanceStudy();
        }
    });
    document.getElementById('exit-study').addEventListener('click', exitStudy);
    document.getElementById('finish-study').addEventListener('click', exitStudy);

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
            const visibleEntries = entryFilter
                ? entries.filter((entry) => entry.boxes[entryFilter.direction] === entryFilter.box)
                : entries;
            visibleEntries.forEach((entry) => selectedEntryIds.add(entry.id));
        }
        renderEntries();
    });

    document.getElementById('box-distribution').addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="filter-box"]');
        if (!button) {
            return;
        }
        entryFilter = button.dataset.direction && button.dataset.box
            ? { direction: button.dataset.direction, box: Number(button.dataset.box) }
            : null;
        selectedEntryIds.clear();
        renderCollectionStatistics();
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
        const saveAndAddAnother = wasNewEntry &&
            (event.submitter?.id === 'save-entry-add-another' || saveAndAddAnotherRequested);
        saveAndAddAnotherRequested = false;
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
