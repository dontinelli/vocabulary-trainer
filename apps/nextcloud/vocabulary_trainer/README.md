# Vocabulary Trainer app

This directory contains the installable Nextcloud app for the vocabulary trainer project.

## Purpose

The app is built around a simple but extensible model:

- A user owns one or more vocabulary collections.
- Each collection has a user-selected source language and target language.
- Each collection contains term pairs.
- Learning progress is tracked per user, entry, and translation direction.
- The default UI language is English and German, but the vocabulary languages are user-defined and can vary per collection.

## Initial structure

```text
apps/
└── nextcloud/
    └── vocabulary_trainer/
        ├── appinfo/
        │   ├── info.xml
        │   └── routes.php
        ├── css/
        │   └── app.css
        ├── l10n/
        │   └── de.json
        ├── js/
        │   └── app.js
        ├── lib/
        │   ├── AppInfo/
        │   │   └── Application.php
        │   ├── Controller/
        │   │   ├── CollectionController.php
        │   │   └── PageController.php
        │   ├── Migration/
        │   │   └── Version1000Date20261111092900.php
        │   └── Service/
        │       └── CollectionService.php
        ├── templates/
        │   └── main.php
        └── README.md
```

## Notes

This is intentionally a minimal app skeleton. It is not yet a full product implementation. It is meant to provide a working Nextcloud app foundation for ongoing development.

## Installation setup

The app's first schema setup is a versioned database migration in `lib/Migration`. Nextcloud runs it when the app is enabled and applies later migrations during app upgrades. The migration creates the collections, entries, progress, and user-settings tables; each collection stores its own box count, while `vocabulary_user_settings` stores the initial per-user study settings. It does not seed user data or alter schema during requests.

## Collections API

The collection interface uses authenticated Nextcloud app routes and persists changes in `vocabulary_collections`:

| Method | Route | Action |
| --- | --- | --- |
| GET | `/apps/vocabulary_trainer/collections` | List the signed-in user's collections and entry counts |
| POST | `/apps/vocabulary_trainer/collections` | Create a collection |
| PUT | `/apps/vocabulary_trainer/collections/{id}` | Update a collection |
| DELETE | `/apps/vocabulary_trainer/collections/{id}` | Delete a collection and its dependent entries |

Collection writes require Nextcloud's request-token/CSRF protection. The owner ID comes from the signed-in session and is never accepted from request data. Create and update validate collection names, language tags, distinct source and target languages, and the 2–10 study-box range. Records belonging to other users are not returned or modifiable through these routes.

Collections are persistent. Entry management, study sessions, and progress tracking remain future work; the current dashboard statistics are still placeholders.

The layout uses Nextcloud theme variables, keyboard-operable controls, accessible dialogs, and translated interface text.

## Tests

Run the dependency-free collection service tests from the repository root:

```sh
php apps/nextcloud/vocabulary_trainer/tests/CollectionServiceTest.php
```

They exercise collection validation, returned data mapping, writes, owner-scoped queries, session authentication, and controller response codes using lightweight Nextcloud/database test doubles.

## Data removal

Nextcloud removes an app's files when it is removed, but it does not automatically drop app-owned database tables. In Nextcloud 35, registered uninstall repair steps also run when an app is disabled, so they cannot safely be used to delete user data only on permanent removal. To preserve data when the app is temporarily disabled, this app does not register a destructive uninstall step. Removing the app therefore leaves its database tables in place until a safe uninstall-only cleanup mechanism is available.
