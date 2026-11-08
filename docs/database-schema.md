# Database schema draft

This is a logical schema for the Nextcloud MVP. Create it using versioned Nextcloud migrations and the platform database abstraction so it remains portable across supported database engines. Table names below are app-specific logical names; use Nextcloud's schema tools to apply the platform table prefix. Avoid database-specific SQL and confirm the exact migration APIs against the selected minimum Nextcloud version.

## Tables

### `vocabulary_collections`

One private collection owned by one Nextcloud account.

| Column | Type / constraints | Purpose |
| --- | --- | --- |
| `id` | auto-increment integer primary key | Internal identifier |
| `user_id` | string, not null, indexed | Nextcloud owner UID |
| `name` | string, not null | Collection title |
| `source_language` | string(64), not null | BCP 47 language tag selected by the user for source terms (e.g. `en`, `de`, `fr`, `zh-Hans`) |
| `target_language` | string(64), not null | BCP 47 language tag selected by the user for target terms; must differ from source |
| `created_at` | datetime, not null | Creation timestamp (UTC) |
| `updated_at` | datetime, not null | Last modification timestamp (UTC) |

Index: `user_id`. Collection names need not be unique for one user. Language fields are not restricted to English and German; validate the identifiers in the application and use a language chooser whose entries can be extended independently of interface translations. Always scope reads, updates, and deletes by both collection ID and current user's UID.

### `vocabulary_entries`

A source/target word or phrase pair belonging to a collection.

| Column | Type / constraints | Purpose |
| --- | --- | --- |
| `id` | auto-increment integer primary key | Internal identifier |
| `collection_id` | integer, not null, indexed | Owning collection |
| `source_term` | text/string, not null | Term in the collection's selected source language |
| `target_term` | text/string, not null | Term in the collection's selected target language |
| `notes` | text, nullable | Optional usage or context note |
| `created_at` | datetime, not null | Creation timestamp (UTC) |
| `updated_at` | datetime, not null | Last modification timestamp (UTC) |

Index: `collection_id`. Enforce collection ownership in the service layer when accessing an entry. Delete associated progress when an entry is deleted. This draft uses one accepted term per side; a separate accepted-answers table can be added if alternatives are required.

### `vocabulary_progress`

A user's learning state for one entry in one translation direction. A row is created when that user first studies the pair in that direction.

| Column | Type / constraints | Purpose |
| --- | --- | --- |
| `id` | auto-increment integer primary key | Internal identifier |
| `user_id` | string, not null | Learner UID; allows future shared collections with per-user progress |
| `entry_id` | integer, not null | Vocabulary entry |
| `direction` | string(16), not null | `source_to_target` or `target_to_source` |
| `box` | small integer, not null, default 1 | Current box; valid range 1 through the user's configured box count |
| `next_review_at` | datetime, nullable, indexed with `user_id` | Earliest scheduled review time; UTC |
| `last_answered_at` | datetime, nullable | Most recent answer time (UTC) |
| `created_at` | datetime, not null | Progress creation timestamp (UTC) |
| `updated_at` | datetime, not null | Progress modification timestamp (UTC) |

Unique constraint: (`user_id`, `entry_id`, `direction`). Indexes: (`user_id`, `next_review_at`) for due-card selection and `entry_id` for cleanup. Store only one progress row per user/entry/direction. The configured box count belongs in a validated per-user preference, with a default of 5; do not duplicate it in every progress row.

## Relationships and lifecycle

```text
vocabulary_collections 1 ─── * vocabulary_entries
vocabulary_entries     1 ─── * vocabulary_progress
```

These are logical relationships. Use the supported Nextcloud schema abstractions and verify database portability before adding database-level foreign-key constraints. In the application, verify ownership through the collection before exposing or modifying its entries. On collection deletion, remove its entries and their progress in a transaction where supported; on entry deletion, remove its progress. Do not rely on client-provided IDs as proof of ownership.

## Migration and validation notes

- Add the initial tables in a versioned migration; add future changes in new migrations rather than editing a migration already shipped.
- Use platform schema types, indexes, constraints, and table-prefix behavior; avoid vendor-specific types, SQL, and reserved column names.
- Validate language tags in the application and reject equal source/target languages. Do not enforce an `en`/`de`-only database constraint; those are the initial UI languages, not vocabulary language limits.
- Validate `direction` against the two supported values and `box` against the user's current configured range.
- Ensure answer submission and progress updates are atomic to prevent concurrent answers from corrupting progress.
- Do not persist a history of every answer in the MVP. Add a dedicated answer-history table only if analytics or review auditing becomes a requirement.
- Define how deleting a Nextcloud user affects owned collections and progress in accordance with the platform's account lifecycle hooks before release.
