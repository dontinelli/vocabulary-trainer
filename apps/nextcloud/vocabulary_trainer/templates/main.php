<?php

declare(strict_types=1);

$strings = [
    'addCollection' => $l->t('Add collection'),
    'editCollection' => $l->t('Edit collection'),
    'boxCount' => $l->t('Study boxes'),
    'learningProgress' => $l->t('Learning progress'),
    'entries' => $l->t('entries'),
    'edit' => $l->t('Edit'),
    'remove' => $l->t('Remove'),
    'removePrompt' => $l->t('Remove "{name}" and all its entries? This cannot be undone.'),
    'languageError' => $l->t('Choose different source and target languages.'),
    'collectionCreated' => $l->t('Collection saved.'),
    'collectionUpdated' => $l->t('Collection updated.'),
    'collectionRemoved' => $l->t('Collection removed.'),
    'networkError' => $l->t('Could not connect to Nextcloud. Check your connection and try again.'),
    'apiNotFound' => $l->t('The app API route was not found (HTTP {status}): {path}. Disable and re-enable the app to refresh its routes.'),
    'httpError' => $l->t('The server returned an unexpected response (HTTP {status}). Check the Nextcloud log for details.'),
    'invalidResponse' => $l->t('Nextcloud returned a response that was not valid JSON (HTTP {status}). Check the Nextcloud log for details.'),
    'openCollection' => $l->t('Open collection'),
    'backToCollections' => $l->t('Back to collections'),
    'addEntry' => $l->t('Add word pair'),
    'editEntry' => $l->t('Edit word pair'),
    'sourceTerm' => $l->t('Word or phrase'),
    'targetTerm' => $l->t('Translation'),
    'notes' => $l->t('Notes'),
    'selectEntry' => $l->t('Select word pair'),
    'selectAll' => $l->t('Select all'),
    'selectedCount' => $l->t('{count} selected'),
    'moveSelected' => $l->t('Move selected'),
    'deleteSelected' => $l->t('Delete selected'),
    'chooseCollection' => $l->t('Choose collection'),
    'confirmDeleteEntries' => $l->t('Delete {count} selected word pairs? This cannot be undone.'),
    'entrySaved' => $l->t('Word pair saved.'),
    'entryUpdated' => $l->t('Word pair updated.'),
    'entriesMoved' => $l->t('Word pairs moved.'),
    'entriesDeleted' => $l->t('Word pairs deleted.'),
    'entryNameRequired' => $l->t('Enter both a word or phrase and its translation.'),
    'selectEntriesError' => $l->t('Select one or more word pairs.'),
    'noEntries' => $l->t('No word pairs yet. Add one to get started.'),
    'noPairsInBox' => $l->t('No word pairs are in this box.'),
    'allPairs' => $l->t('All word pairs'),
    'entriesLoadFailed' => $l->t('Could not load word pairs.'),
    'requestFailed' => $l->t('The request could not be completed.'),
    'startLearning' => $l->t('Start learning'),
    'learningSetup' => $l->t('Set up a learning session'),
    'direction' => $l->t('Translation direction'),
    'sourceToTarget' => $l->t('Source to target'),
    'targetToSource' => $l->t('Target to source'),
    'learningMode' => $l->t('Learning mode'),
    'typedMode' => $l->t('Typed answer'),
    'multipleChoiceMode' => $l->t('Multiple choice'),
    'mixedMode' => $l->t('Mixed'),
    'choiceModeLimit' => $l->t('Multiple choice and mixed modes require at least 11 matching word pairs.'),
    'sessionSelection' => $l->t('Pairs to study'),
    'automaticSelection' => $l->t('New and due pairs'),
    'newPairsSelection' => $l->t('New pairs'),
    'boxSelection' => $l->t('Pairs in a box'),
    'errorRateSelection' => $l->t('Error rate above a threshold'),
    'notPracticedSelection' => $l->t('Not practiced recently'),
    'boxNumber' => $l->t('Box'),
    'errorRateThreshold' => $l->t('Error rate greater than'),
    'daysSincePractice' => $l->t('Days since last practice'),
    'startWithNew' => $l->t('Ask new pairs first'),
    'includeFinalBox' => $l->t('Include the final box in automatic sessions'),
    'answerDelaySeconds' => $l->t('Seconds to show the answer'),
    'limitResponseTime' => $l->t('Limit time to answer'),
    'questionTimeLimitSeconds' => $l->t('Seconds per answer'),
    'startSession' => $l->t('Start session'),
    'noQuestions' => $l->t('No word pairs match this session.'),
    'sessionProgress' => $l->t('Question {current} of {total}'),
    'submitAnswer' => $l->t('Check answer'),
    'correctAnswer' => $l->t('Correct!'),
    'incorrectAnswer' => $l->t('Not quite. The correct answer is: {answer}'),
    'sessionComplete' => $l->t('Session complete'),
    'sessionSummary' => $l->t('You answered {correct} of {total} correctly.'),
    'returnToCollection' => $l->t('Return to collection'),
    'pauseAutomaticAdvance' => $l->t('Pause automatic advance'),
    'resumeAutomaticAdvance' => $l->t('Resume automatic advance'),
    'nextQuestionTimer' => $l->t('Time remaining before the next question'),
    'responseTimerLabel' => $l->t('Time remaining to answer'),
];
?>

<div id="app-content">
    <main class="vt-app" id="vocabulary-trainer" data-strings="<?php p(json_encode($strings, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)); ?>">
        <div class="vt-page">
            <p class="vt-error-alert" id="collection-load-error" role="alert" aria-live="assertive" hidden></p>

            <div id="overview-content">
            <header class="vt-header">
                <div class="vt-header-brand">
                    <span class="vt-app-icon" aria-hidden="true"></span>
                    <div>
                        <p class="vt-eyebrow"><?php p($l->t('Vocabulary Trainer')); ?></p>
                        <h1><?php p($l->t('Learn at your pace')); ?></h1>
                        <p class="vt-subtitle"><?php p($l->t('Organize your vocabulary and keep track of your learning.')); ?></p>
                    </div>
                </div>
            </header>

            <p class="vt-info-notice" role="status">
                <?php p($l->t('Your collections are saved to your Nextcloud account.')); ?>
            </p>

            <section class="vt-section" aria-labelledby="progress-heading">
                <div class="vt-section-heading">
                    <div>
                        <h2 id="progress-heading"><?php p($l->t('Your progress')); ?></h2>
                        <p><?php p($l->t('A summary of your recent learning.')); ?></p>
                    </div>
                </div>
                <div class="vt-dashboard" role="region" aria-label="<?php p($l->t('Progress dashboard placeholder')); ?>">
                    <div class="vt-progress-placeholder">
                        <span class="vt-placeholder-mark" aria-hidden="true">↗</span>
                        <div>
                            <h3><?php p($l->t('Your learning overview will appear here')); ?></h3>
                            <p><?php p($l->t('Study some vocabulary to see your progress, review activity, and collection statistics.')); ?></p>
                        </div>
                    </div>
                    <div class="vt-stat-placeholder">
                        <span><?php p($l->t('Reviewed today')); ?></span>
                        <strong>—</strong>
                    </div>
                    <div class="vt-stat-placeholder">
                        <span><?php p($l->t('Learned words')); ?></span>
                        <strong>—</strong>
                    </div>
                </div>
            </section>

            <section class="vt-section" aria-labelledby="collections-heading">
                <div class="vt-section-heading">
                    <div>
                        <h2 id="collections-heading"><?php p($l->t('Your collections')); ?></h2>
                        <p><?php p($l->t('Keep vocabulary organized by language pair.')); ?></p>
                    </div>
                    <button class="vt-button vt-button--primary" id="add-collection" type="button">
                        <span aria-hidden="true">+</span>
                        <?php p($l->t('Add collection')); ?>
                    </button>
                </div>
                <div class="vt-collection-list" id="collection-list" aria-live="polite"></div>
                <div class="vt-empty-state" id="collection-empty" hidden>
                    <span class="vt-empty-icon" aria-hidden="true">＋</span>
                    <h3><?php p($l->t('No collections yet')); ?></h3>
                    <p><?php p($l->t('Create a collection to organize vocabulary by language pair.')); ?></p>
                    <button class="vt-button" type="button" data-action="add"><?php p($l->t('Add collection')); ?></button>
                </div>
            </section>
            </div>

            <section class="vt-section vt-entry-view" id="collection-detail" aria-labelledby="detail-heading" hidden>
                <div class="vt-detail-heading">
                    <div>
                        <button class="vt-button vt-button--back" type="button" id="back-to-collections">
                            ← <?php p($l->t('Back to collections')); ?>
                        </button>
                        <h2 id="detail-heading"></h2>
                        <p id="detail-languages" class="vt-card-languages"></p>
                    </div>
                    <button class="vt-button vt-button--primary" type="button" id="add-entry">
                        <span aria-hidden="true">+</span>
                        <?php p($l->t('Add word pair')); ?>
                    </button>
                    <button class="vt-button vt-button--primary" type="button" id="start-learning">
                        <?php p($l->t('Start learning')); ?>
                    </button>
                </div>
                <section class="vt-collection-dashboard" aria-labelledby="collection-dashboard-heading">
                    <div class="vt-section-heading">
                        <div>
                            <h3 id="collection-dashboard-heading"><?php p($l->t('Collection overview')); ?></h3>
                            <p><?php p($l->t('Accuracy and learning progress combine both translation directions. Select a direction to view its box distribution.')); ?></p>
                        </div>
                    </div>
                    <div class="vt-direction-toggle" id="direction-toggle" role="group" aria-label="<?php p($l->t('Translation direction')); ?>">
                        <button class="vt-direction-button" type="button" data-direction="source_to_target" aria-pressed="true">
                            <?php p($l->t('Source to target')); ?>
                        </button>
                        <button class="vt-direction-button" type="button" data-direction="target_to_source" aria-pressed="false">
                            <?php p($l->t('Target to source')); ?>
                        </button>
                    </div>
                    <div class="vt-collection-stats">
                        <div class="vt-stat-placeholder">
                            <span><?php p($l->t('Study boxes')); ?></span>
                            <strong id="collection-box-count">—</strong>
                        </div>
                        <div class="vt-stat-placeholder">
                            <span><?php p($l->t('Correct answers')); ?></span>
                            <strong id="collection-accuracy">—</strong>
                        </div>
                        <div class="vt-stat-placeholder">
                            <span><?php p($l->t('Learning progress')); ?></span>
                            <strong id="collection-learning-progress">—</strong>
                        </div>
                    </div>
                    <div class="vt-box-distribution" id="box-distribution" aria-label="<?php p($l->t('Filter word pairs by study box')); ?>"></div>
                </section>
                <p class="vt-error-alert" id="entry-load-error" role="alert" aria-live="assertive" hidden></p>
                <div class="vt-bulk-toolbar" id="bulk-toolbar">
                    <label class="vt-select-all">
                        <input id="select-all-entries" type="checkbox">
                        <?php p($l->t('Select all')); ?>
                    </label>
                    <span id="selected-count" aria-live="polite"></span>
                    <label class="vt-move-select">
                        <span class="vt-visually-hidden"><?php p($l->t('Choose collection')); ?></span>
                        <select id="target-collection">
                            <option value=""><?php p($l->t('Choose collection')); ?></option>
                        </select>
                    </label>
                    <button class="vt-button" type="button" id="move-selected" disabled><?php p($l->t('Move selected')); ?></button>
                    <button class="vt-button vt-button--danger" type="button" id="delete-selected" disabled><?php p($l->t('Delete selected')); ?></button>
                </div>
                <ul class="vt-entry-list" id="entry-list" aria-live="polite"></ul>
                <div class="vt-empty-state" id="entry-empty" hidden>
                    <h3 id="entry-empty-message"><?php p($l->t('No word pairs yet. Add one to get started.')); ?></h3>
                </div>
            </section>

            <section class="vt-section vt-study-view" id="study-view" aria-labelledby="study-heading" hidden>
                <div class="vt-detail-heading">
                    <div>
                        <button class="vt-button vt-button--back" type="button" id="exit-study">
                            ← <?php p($l->t('Return to collection')); ?>
                        </button>
                        <h2 id="study-heading"><?php p($l->t('Learning session')); ?></h2>
                        <p id="study-language-direction" class="vt-card-languages"></p>
                    </div>
                </div>
                <div id="study-question-view" hidden>
                    <p id="study-question-progress" class="vt-study-progress"></p>
                    <div class="vt-study-card">
                        <div class="vt-study-timer-row" id="study-timer-controls" hidden>
                            <button class="vt-pin-button" id="pause-study-timer" type="button" aria-label="<?php p($l->t('Pause automatic advance')); ?>" title="<?php p($l->t('Pause automatic advance')); ?>" aria-pressed="false" hidden>
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M14 3 21 10l-4 1-3 5-1 5-2-2 2-6-5-5-4 1-2-2 5-1 5-5z"/>
                                </svg>
                            </button>
                        </div>
                        <progress class="vt-study-timer" id="study-auto-advance" value="100" max="100" aria-label="<?php p($l->t('Time remaining before the next question')); ?>" hidden></progress>
                        <h3 id="study-prompt"></h3>
                        <form id="study-answer-form" class="vt-study-answer-form">
                            <div id="study-answer-area"></div>
                            <p class="vt-error-alert" id="study-answer-error" role="alert" hidden></p>
                            <button class="vt-button vt-button--primary" id="submit-study-answer" type="submit"><?php p($l->t('Check answer')); ?></button>
                        </form>
                        <p class="vt-study-feedback" id="study-feedback" role="status" tabindex="-1" hidden></p>
                        <p class="vt-study-hint"><?php p($l->t('Click the card outside the answer controls to move to the next step.')); ?></p>
                    </div>
                </div>
                <div class="vt-empty-state" id="study-empty" hidden>
                    <h3><?php p($l->t('No word pairs match this session.')); ?></h3>
                </div>
                <div class="vt-empty-state" id="study-complete" hidden>
                    <h3><?php p($l->t('Session complete')); ?></h3>
                    <p id="study-summary"></p>
                    <button class="vt-button" type="button" id="finish-study"><?php p($l->t('Return to collection')); ?></button>
                </div>
            </section>
        </div>

        <dialog class="vt-dialog" id="collection-dialog" aria-labelledby="collection-dialog-title" hidden>
            <form id="collection-form">
                <div class="vt-dialog-header">
                    <h2 id="collection-dialog-title"><?php p($l->t('Add collection')); ?></h2>
                    <button class="vt-icon-button" type="button" data-close="collection-dialog" aria-label="<?php p($l->t('Close')); ?>">×</button>
                </div>
                <p class="vt-error-alert" id="collection-form-error" role="alert" aria-live="assertive" hidden></p>
                <div class="vt-form-fields">
                    <label>
                        <span><?php p($l->t('Collection name')); ?></span>
                        <input name="name" type="text" maxlength="255" required autocomplete="off">
                    </label>
                    <div class="vt-language-fields">
                        <label>
                            <span><?php p($l->t('Source language tag')); ?></span>
                            <input name="sourceLanguage" type="text" maxlength="64" required placeholder="en" autocomplete="off">
                        </label>
                        <label>
                            <span><?php p($l->t('Target language tag')); ?></span>
                            <input name="targetLanguage" type="text" maxlength="64" required placeholder="de" autocomplete="off">
                        </label>
                    </div>
                    <label>
                        <span><?php p($l->t('Study boxes')); ?></span>
                        <input name="boxCount" type="number" min="2" max="10" value="5" required>
                        <small><?php p($l->t('Choose between 2 and 10 boxes for this collection.')); ?></small>
                    </label>
                </div>
                <div class="vt-dialog-actions">
                    <button class="vt-button" type="button" data-close="collection-dialog"><?php p($l->t('Cancel')); ?></button>
                    <button class="vt-button vt-button--primary" type="submit"><?php p($l->t('Save collection')); ?></button>
                </div>
            </form>
        </dialog>

        <dialog class="vt-dialog" id="study-setup-dialog" aria-labelledby="study-setup-title" hidden>
            <form id="study-setup-form">
                <div class="vt-dialog-header">
                    <h2 id="study-setup-title"><?php p($l->t('Set up a learning session')); ?></h2>
                    <button class="vt-icon-button" type="button" data-close="study-setup-dialog" aria-label="<?php p($l->t('Close')); ?>">×</button>
                </div>
                <p class="vt-error-alert" id="study-setup-error" role="alert" hidden></p>
                <div class="vt-form-fields">
                    <label>
                        <span><?php p($l->t('Translation direction')); ?></span>
                        <select name="direction" required>
                            <option value="source_to_target"></option>
                            <option value="target_to_source"></option>
                        </select>
                    </label>
                    <label>
                        <span><?php p($l->t('Learning mode')); ?></span>
                        <select name="mode" required>
                            <option value="typed"><?php p($l->t('Typed answer')); ?></option>
                            <option value="multiple-choice"><?php p($l->t('Multiple choice')); ?></option>
                            <option value="mixed"><?php p($l->t('Mixed')); ?></option>
                        </select>
                    </label>
                    <small><?php p($l->t('Multiple choice and mixed modes require at least 11 matching word pairs.')); ?></small>
                    <label>
                        <span><?php p($l->t('Pairs to study')); ?></span>
                        <select name="selection" required>
                            <option value="automatic"><?php p($l->t('New and due pairs')); ?></option>
                            <option value="new"><?php p($l->t('New pairs')); ?></option>
                            <option value="box"><?php p($l->t('Pairs in a box')); ?></option>
                            <option value="error-rate"><?php p($l->t('Error rate above a threshold')); ?></option>
                            <option value="not-practiced"><?php p($l->t('Not practiced recently')); ?></option>
                        </select>
                    </label>
                    <label id="study-box-option" hidden>
                        <span><?php p($l->t('Box')); ?></span>
                        <select name="box"></select>
                    </label>
                    <label id="study-error-rate-option" hidden>
                        <span><?php p($l->t('Error rate greater than')); ?> (%)</span>
                        <input name="errorRate" type="number" min="0" max="100" value="50">
                    </label>
                    <label id="study-days-option" hidden>
                        <span><?php p($l->t('Days since last practice')); ?></span>
                        <input name="days" type="number" min="1" max="3650" value="7">
                    </label>
                    <label class="vt-checkbox-field">
                        <input name="startWithNew" type="checkbox" checked>
                        <span><?php p($l->t('Ask new pairs first')); ?></span>
                    </label>
                    <label class="vt-checkbox-field">
                        <input name="includeFinalBox" type="checkbox">
                        <span><?php p($l->t('Include the final box in automatic sessions')); ?></span>
                    </label>
                    <label>
                        <span><?php p($l->t('Seconds to show the answer')); ?></span>
                        <input name="answerDelaySeconds" type="number" min="1" max="30" step="1" value="5" required>
                    </label>
                    <label class="vt-checkbox-field">
                        <input name="limitResponseTime" type="checkbox">
                        <span><?php p($l->t('Limit time to answer (leave unchecked for unlimited)')); ?></span>
                    </label>
                    <label>
                        <span><?php p($l->t('Seconds per answer')); ?></span>
                        <input name="questionTimeLimitSeconds" type="number" min="1" max="300" step="1" value="30" required disabled>
                    </label>
                </div>
                <div class="vt-dialog-actions">
                    <button class="vt-button" type="button" data-close="study-setup-dialog"><?php p($l->t('Cancel')); ?></button>
                    <button class="vt-button vt-button--primary" id="start-study-session" type="submit"><?php p($l->t('Start session')); ?></button>
                </div>
            </form>
        </dialog>

        <dialog class="vt-dialog" id="entry-dialog" aria-labelledby="entry-dialog-title" hidden>
            <form id="entry-form">
                <div class="vt-dialog-header">
                    <h2 id="entry-dialog-title"><?php p($l->t('Add word pair')); ?></h2>
                    <button class="vt-icon-button" type="button" data-close="entry-dialog" aria-label="<?php p($l->t('Close')); ?>">×</button>
                </div>
                <p class="vt-error-alert" id="entry-form-error" role="alert" aria-live="assertive" hidden></p>
                <div class="vt-form-fields">
                    <label>
                        <span><?php p($l->t('Word or phrase')); ?></span>
                        <textarea name="sourceTerm" rows="2" maxlength="65535" required></textarea>
                    </label>
                    <label>
                        <span><?php p($l->t('Translation')); ?></span>
                        <textarea name="targetTerm" rows="2" maxlength="65535" required></textarea>
                    </label>
                    <label>
                        <span><?php p($l->t('Notes')); ?></span>
                        <textarea name="notes" rows="3" maxlength="65535"></textarea>
                    </label>
                </div>
                <div class="vt-dialog-actions">
                    <button class="vt-button" type="button" data-close="entry-dialog"><?php p($l->t('Cancel')); ?></button>
                    <button class="vt-button" id="save-entry-add-another" type="submit"><?php p($l->t('Save and add another')); ?></button>
                    <button class="vt-button vt-button--primary" type="submit"><?php p($l->t('Save word pair')); ?></button>
                </div>
            </form>
        </dialog>

        <dialog class="vt-dialog" id="bulk-delete-dialog" aria-labelledby="bulk-delete-title" hidden>
            <div class="vt-dialog-header">
                <h2 id="bulk-delete-title"><?php p($l->t('Delete selected')); ?></h2>
                <button class="vt-icon-button" type="button" data-close="bulk-delete-dialog" aria-label="<?php p($l->t('Close')); ?>">×</button>
            </div>
            <p id="bulk-delete-prompt"></p>
            <p class="vt-error-alert" id="bulk-delete-error" role="alert" aria-live="assertive" hidden></p>
            <div class="vt-dialog-actions">
                <button class="vt-button" type="button" data-close="bulk-delete-dialog"><?php p($l->t('Cancel')); ?></button>
                <button class="vt-button vt-button--danger" type="button" id="confirm-delete-selected"><?php p($l->t('Delete selected')); ?></button>
            </div>
        </dialog>

        <dialog class="vt-dialog vt-dialog--confirm" id="remove-dialog" aria-labelledby="remove-dialog-title" hidden>
            <div class="vt-dialog-header">
                <h2 id="remove-dialog-title"><?php p($l->t('Remove collection')); ?></h2>
                <button class="vt-icon-button" type="button" data-close="remove-dialog" aria-label="<?php p($l->t('Close')); ?>">×</button>
            </div>
            <p class="vt-error-alert" id="remove-error" role="alert" aria-live="assertive" hidden></p>
            <p id="remove-prompt"></p>
            <div class="vt-dialog-actions">
                <button class="vt-button" type="button" data-close="remove-dialog"><?php p($l->t('Cancel')); ?></button>
                <button class="vt-button vt-button--danger" id="confirm-remove" type="button"><?php p($l->t('Remove collection')); ?></button>
            </div>
        </dialog>

        <p class="vt-announcement" id="collection-announcement" role="status" aria-live="polite"></p>
    </main>
</div>
