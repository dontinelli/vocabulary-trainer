# Architecture draft

This document is a starting design for the Nextcloud-first MVP, informed by the [Nextcloud developer documentation](https://nextcloud.com/developer/). The current development is based on Nextcloud 35. Support for upcoming versions and possible backward compatibility may be tested/added once a running prototype is available.

## Proposed repository layout

```text
apps/
├── nextcloud/
│   └── vocabulary_trainer/       # Installable Nextcloud app (app ID: vocabulary_trainer)
│       ├── appinfo/
│       │   ├── info.xml           # App metadata, dependencies, version
│       │   └── routes.php         # Declarative routes
│       ├── lib/
│       │   ├── AppInfo/           # Application bootstrap and DI registration
│       │   ├── Controller/        # Thin HTTP controllers
│       │   ├── Db/                # Entities and database mappers
│       │   ├── Migration/         # Versioned schema migrations
│       │   └── Service/           # Ownership checks and application/business logic
│       ├── src/                   # Vue/TypeScript frontend source
│       ├── css/                   # Built styles, if applicable
│       ├── js/                    # Built frontend assets
│       ├── tests/                 # Unit and integration tests
│       ├── composer.json
│       ├── package.json
│       └── README.md
└── android/                       # Future, independent Android client

docs/
├── architecture.md
├── database-schema.md
└── project-spec.md
```

Keep the Nextcloud app self-contained under its app directory. The Android app should be a separate client of a versioned API rather than sharing server implementation code.

## Nextcloud implementation principles

- Use the Nextcloud app framework and dependency injection; keep controllers thin and put business rules in services.
- Use app routes and the platform's request/response abstractions. Require authentication for private vocabulary and progress endpoints, and enforce collection ownership in service/repository operations on every request.
- Use Nextcloud's database connection/query-builder and mapper abstractions, not raw SQL or direct database access. Create and change tables through versioned app migrations; never make schema changes during normal requests.
- Use the current-user service to identify the owner. Do not accept an owner UID from an untrusted request body.
- Follow platform CSRF/request-token conventions for state-changing requests. Return only data the current user is authorized to see.
- Keep frontend source separate from generated assets and use the supported Nextcloud frontend build tooling. Avoid committing secrets or local instance data.
- Use app translation facilities for user-visible text. The initial interface translations are English and German; this is separate from the vocabulary languages selected for individual collections.

## Initial endpoint sketch

The exact routes should be finalized after the supported Nextcloud versions are chosen. Use the app's route declarations and version the API path so a future Android client can depend on a stable contract.

- Collections: list, create, read, update, delete.
- Entries scoped to a collection: list, create, update, delete.
- Study: request a question, submit an answer, and retrieve/update study preferences.

Study-answer handling should be server-side: validate ownership, compare answers according to a documented normalization policy, and update progress atomically. Multiple-choice distractors must be drawn from the same user's collection and target language. Do not include the expected answer in a question response before the answer is submitted.

## Collection languages

The UI is initially localized in English and German, but these are not restrictions on vocabulary content. On collection creation, the user selects a source and target language from the app's language chooser. Store language identifiers using BCP 47 language tags (for example `en`, `de`, `fr`, `zh-Hans`, or `pt-BR`). The language selector should support with autofill (e.g. based on a curated list) but the input should not be restricted, so as to allow also other learning tasks (e.g. declination, conjugation). Require source and target to differ.

## Learning model

Track progress per user, entry, and translation direction. The default is five boxes; store each user's configured box count as a validated user preference (initially 2–10, default 5), not as a global app setting. New progress begins in box 1. A correct answer advances at most one box; an incorrect answer resets to box 1 (or one box lower). The scheduler should use the current configured box count when choosing due work, so changing the setting does not require migrating progress rows.

A first scheduling policy can assign increasing review intervals to higher boxes. Keep that mapping in application code initially; it can become a user preference later if user testing shows a need. Progress rows store the next due time so the scheduler can evolve without changing the meaning of box placement.

## Open decisions before implementation

- The scope and source of the initial bundled language chooser list, and whether users can enter a language tag not in that list.
- Whether one word pair is strictly one-to-one in the MVP or whether entries need multiple accepted translations immediately.
- Answer normalization rules (case, whitespace, punctuation, and accepted alternatives).
- The initial interval assigned to each box and the behavior when a user changes box count.
- API route naming/versioning and frontend build choices for the selected Nextcloud compatibility range.
