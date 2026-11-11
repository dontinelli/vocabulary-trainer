<?php

declare(strict_types=1);

$strings = [
    'addCollection' => $l->t('Add collection'),
    'editCollection' => $l->t('Edit collection'),
    'boxCount' => $l->t('Study boxes'),
    'entries' => $l->t('entries'),
    'edit' => $l->t('Edit'),
    'remove' => $l->t('Remove'),
    'removePrompt' => $l->t('Remove "{name}"? This preview action cannot be undone.'),
    'languageError' => $l->t('Choose different source and target languages.'),
    'collectionCreated' => $l->t('Collection added to this preview.'),
    'collectionUpdated' => $l->t('Preview collection updated.'),
    'collectionRemoved' => $l->t('Preview collection removed.'),
];
?>

<div id="app-content">
    <main class="vt-app" id="vocabulary-trainer" data-strings="<?php p(json_encode($strings, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)); ?>">
        <div class="vt-page">
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

            <p class="vt-preview-notice" role="status">
                <?php p($l->t('Preview only: collection changes are temporary and are not saved yet.')); ?>
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

        <dialog class="vt-dialog" id="collection-dialog" aria-labelledby="collection-dialog-title">
            <form id="collection-form">
                <div class="vt-dialog-header">
                    <h2 id="collection-dialog-title"><?php p($l->t('Add collection')); ?></h2>
                    <button class="vt-icon-button" type="button" data-close="collection-dialog" aria-label="<?php p($l->t('Close')); ?>">×</button>
                </div>
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
                <p class="vt-form-error" id="collection-form-error" role="alert" hidden></p>
                <div class="vt-dialog-actions">
                    <button class="vt-button" type="button" data-close="collection-dialog"><?php p($l->t('Cancel')); ?></button>
                    <button class="vt-button vt-button--primary" type="submit"><?php p($l->t('Save collection')); ?></button>
                </div>
            </form>
        </dialog>

        <dialog class="vt-dialog vt-dialog--confirm" id="remove-dialog" aria-labelledby="remove-dialog-title">
            <div class="vt-dialog-header">
                <h2 id="remove-dialog-title"><?php p($l->t('Remove collection')); ?></h2>
                <button class="vt-icon-button" type="button" data-close="remove-dialog" aria-label="<?php p($l->t('Close')); ?>">×</button>
            </div>
            <p id="remove-prompt"></p>
            <div class="vt-dialog-actions">
                <button class="vt-button" type="button" data-close="remove-dialog"><?php p($l->t('Cancel')); ?></button>
                <button class="vt-button vt-button--danger" id="confirm-remove" type="button"><?php p($l->t('Remove collection')); ?></button>
            </div>
        </dialog>

        <p class="vt-announcement" id="collection-announcement" role="status" aria-live="polite"></p>
    </main>
</div>
