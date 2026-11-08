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
        │   └── Service/
        │       └── CollectionService.php
        └── README.md
```

## Notes

This is intentionally a minimal app skeleton. It is not yet a full product implementation. It is meant to provide a working Nextcloud app foundation for ongoing development.
