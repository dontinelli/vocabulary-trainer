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
        ├── lib/
        │   ├── AppInfo/
        │   │   └── Application.php
        │   ├── Controller/
        │   │   └── PageController.php
        │   ├── Migration/
        │   │   └── Version1000Date20261111092900.php
        │   └── Service/
        │       └── CollectionService.php
        └── README.md
```

## Notes

This is intentionally a minimal app skeleton. It is not yet a full product implementation. It is meant to provide a working Nextcloud app foundation for ongoing development.

## Installation setup

The app's first schema setup is a versioned database migration in `lib/Migration`. Nextcloud runs it when the app is enabled and applies later migrations during app upgrades. The migration creates the collections, entries, progress, and user-settings tables; each collection stores its own box count, while `vocabulary_user_settings` stores the initial per-user study settings. It does not seed user data or alter schema during requests.
