# Database schema

## 1. Collections

Table: `vocabulary_collections`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint / int PK | internal identifier |
| user_id | varchar(255) | owning Nextcloud user |
| name | varchar(255) | collection title |
| source_language | varchar(64) | BCP 47 language tag, user-selected |
| target_language | varchar(64) | BCP 47 language tag, user-selected |
| created_at | datetime | UTC |
| updated_at | datetime | UTC |

Constraints:
- source_language and target_language must be provided
- source_language != target_language
- rows are always filtered by `user_id`

## 2. Entries

Table: `vocabulary_entries`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint / int PK | internal identifier |
| collection_id | bigint / int FK | reference to collection |
| source_term | text | term in source language |
| target_term | text | term in target language |
| notes | text nullable | optional contextual note |
| created_at | datetime | UTC |
| updated_at | datetime | UTC |

Constraints:
- each entry belongs to exactly one collection
- on collection delete, related entries are deleted as part of cleanup or migration logic

## 3. Study progress

Table: `vocabulary_progress`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint / int PK | internal identifier |
| user_id | varchar(255) | learner |
| entry_id | bigint / int FK | associated entry |
| direction | varchar(32) | source_to_target or target_to_source |
| box | int | current review box; default 1 |
| next_review_at | datetime nullable | used for due review scheduling |
| last_answered_at | datetime nullable | most recent answer timestamp |
| created_at | datetime | UTC |
| updated_at | datetime | UTC |

Constraints:
- unique on (`user_id`, `entry_id`, `direction`)
- `box` should always be within the valid range for the user's configured box count

## 4. User preference

Table: `vocabulary_user_settings` (recommended)

| Field | Type | Notes |
| --- | --- | --- |
| user_id | varchar(255) PK | Nextcloud user |
| box_count | int | default 5, allowed range 2 to 10 |
| study_mode | varchar(32) nullable | default practice preference |
| created_at | datetime | UTC |
| updated_at | datetime | UTC |

This avoids duplicating the box count on every progress row.

## 5. Notes on language handling

The interface language remains English/German. The collection vocabulary language pair is independent and stored as a BCP 47 tag. Example values:

- `en`, `de`
- `fr`, `es`, `pt-BR`
- `zh-Hans`, `ja`

Do not restrict `source_language` or `target_language` to `en` and `de` in the schema.
