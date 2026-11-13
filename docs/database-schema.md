# Database schema

## 1. Collections

Table: `vocabulary_collections`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint / int PK | internal identifier |
| user_id | varchar(255) | owning Nextcloud user |
| name | varchar(255) | collection title |
| description | text nullable | collection description |
| source_language | varchar(64) | BCP 47 language tag, user-selected |
| target_language | varchar(64) | BCP 47 language tag, user-selected |
| box_count | int | default 5, allowed range 2 to 10 |
| created_at | datetime | UTC |
| updated_at | datetime | UTC |

Constraints:
- source_language and target_language must be provided
- source_language != target_language
- box_count is configurable per collection and must be between 2 and 10
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
- collection deletion cascades to its entries

## 3. Study progress

Table: `vocabulary_progress`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint / int PK | internal identifier |
| user_id | varchar(255) | learner |
| entry_id | bigint / int FK | associated entry |
| direction | varchar(32) | source_to_target or target_to_source |
| box | int | current review box; default 1 |
| answered | int | counter of amount the pair has been answered |
| answered_correctly | int | counter of amount the pair has been correctly answered |
| last_answered_at | datetime nullable | most recent answer timestamp |
| next_ask_at | datetime nullable | next test planned at |
| created_at | datetime | UTC |
| updated_at | datetime | UTC |

Constraints:
- unique on (`user_id`, `entry_id`, `direction`)
- `box` should always be within the valid range for its collection's `box_count`
- entry deletion cascades to its progress rows

## 4. User preferences

Table: `vocabulary_user_settings`

| Field | Type | Notes |
| --- | --- | --- |
| user_id | varchar(255) PK | Nextcloud user |
| start_with_new | bool | study new pairs before repeating existing ones |
| include_final_box | bool | continue asking pairs in the final box |
| answer_delay_seconds | int | seconds to show answer feedback before advancing; default 5, range 1–30 |
| question_time_limit_seconds | int | time to answer; 0 means unlimited, otherwise 1–300 seconds |
| return_by_mistake | bool | whether a mistake lowers progress by one box or resets to box 1 |
| created_at | datetime | UTC |
| updated_at | datetime | UTC |

Settings are stored per user. The initial migration creates the baseline table; later versioned migrations may add fields with defaults for existing users.

## 5. Installation and validation

The initial versioned migration creates these tables, indexes, and cascade relationships when the app is enabled or upgraded. Collection language inequality and BCP 47 validation, per-collection box-count bounds, valid progress directions, and ownership checks are enforced by application services; they must not be trusted to request data. User settings are per-user and initialized separately from collection creation.

Nextcloud does not automatically drop app-owned tables when app files are removed. In the supported Nextcloud 35 lifecycle, uninstall repair steps also run when the app is disabled; avoid using them for destructive cleanup when app data must survive a temporary disable. The app currently leaves its tables intact on removal.

## 6. Notes on language handling

The collection vocabulary language pair is independent and stored as a BCP 47 tag. Example values:

- `en`, `de`
- `fr`, `es`
- `zh-Hans`, `ja`

## 7. Collection API

The Nextcloud UI uses these authenticated app routes:

| Method | Route | Behavior |
| --- | --- | --- |
| GET | `/apps/vocabulary_trainer/collections` | Return only the current user's collections, including each collection's entry count |
| POST | `/apps/vocabulary_trainer/collections` | Validate and create a collection owned by the current user |
| PUT | `/apps/vocabulary_trainer/collections/{id}` | Validate and update a collection owned by the current user |
| DELETE | `/apps/vocabulary_trainer/collections/{id}` | Delete a collection owned by the current user |

Write routes retain Nextcloud's request-token/CSRF protection. Ownership is always derived from the authenticated user session, not a request parameter. Updates and deletes are scoped by both collection ID and owner; a missing or other user's collection returns not found. Collection names are required and limited to 255 characters, language tags are validated and must differ, and `box_count` must be between 2 and 10.
