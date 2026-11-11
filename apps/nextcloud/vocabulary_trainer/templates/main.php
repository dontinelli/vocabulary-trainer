<?php

declare(strict_types=1);

$strings = [
    'addCollection' => $l->t('Add collection'),
    'editCollection' => $l->t('Edit collection'),
    'boxCount' => $l->t('Study boxes'),
    'entries' => $l->t('entries'),
    'edit' => $l->t('Edit'),
    'remove' => $l->t('Remove'),
    'removePrompt' => $l->t('Remove "{name}" and all its entries? This cannot be undone.'),
    'languageError' => $l->t('Choose different source and target languages.'),
    'collectionCreated' => $l->t('Collection saved.'),
    'collectionUpdated' => $l->t('Collection updated.'),
    'collectionRemoved' => $l->t('Collection removed.'),
    'networkError' => $l->t('Could not connect to Nextcloud. Check your connection and try again.'),
    'apiNotFound' => $l->t('The app API route was not found (HTTP {status}). Disable and re-enable the app to refresh its routes.'),
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
    'entriesLoadFailed' => $l->t('Could not load word pairs.'),
    'requestFailed' => $l->t('The request could not be completed.'),
];
?>

<div id="app-content">
    <main class="vt-app" id="vocabulary-trainer" data-strings="<?php p(json_encode($strings, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)); ?>">
        <div class="vt-page">
            <p class="vt-error-alert" id="collection-load-error" role="alert" aria-live="assertive" hidden></p>

            <div id="overview-content">
            <header class="vt-header">
                <div>
                    <p class="vt-eyebrow"><?php p($l->t('Vocabulary Trainer')); ?></p>
                    <h1><?php p($l->t('Learn at your pace')); ?></h1>
                    <p class="vt-subtitle"><?php p($l->t('Organize your vocabulary and keep track of your learning.')); ?></p>
                </div>
                <button class="vt-button vt-button--primary" id="add-collection" type="button">
                    <span aria-hidden="true">+</span>
                    <?php p($l->t('Add collection')); ?>
                </button>
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
                </div>
                <section class="vt-collection-dashboard" aria-labelledby="collection-dashboard-heading">
                    <div class="vt-section-heading">
                        <div>
                            <h3 id="collection-dashboard-heading"><?php p($l->t('Collection overview')); ?></h3>
                            <p><?php p($l->t('Collection statistics will appear here.')); ?></p>
                        </div>
                    </div>
                    <div class="vt-collection-stats">
                        <div class="vt-stat-placeholder">
                            <span><?php p($l->t('Word pairs')); ?></span>
                            <strong id="collection-entry-count">—</strong>
                        </div>
                        <div class="vt-stat-placeholder">
                            <span><?php p($l->t('Study boxes')); ?></span>
                            <strong id="collection-box-count">—</strong>
                        </div>
                        <div class="vt-stat-placeholder">
                            <span><?php p($l->t('Learning progress')); ?></span>
                            <strong>—</strong>
                        </div>
                    </div>
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
                    <h3><?php p($l->t('No word pairs yet. Add one to get started.')); ?></h3>
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
