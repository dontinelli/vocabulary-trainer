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
| return_by_mistake | bool | whether a mistake lowers progress by one box or resets to box 1 |
| created_at | datetime | UTC |
| updated_at | datetime | UTC |

These are the initial baseline settings; additional settings may be added later. Their default values and precise behavior should be set by the application when initializing a user's settings.

## 5. Installation and validation

The initial versioned migration creates these tables, indexes, and cascade relationships when the app is enabled or upgraded. Collection language inequality and BCP 47 validation, per-collection box-count bounds, valid progress directions, and ownership checks are enforced by application services; they must not be trusted to request data. User settings are per-user and initialized separately from collection creation.

## 6. Notes on language handling

The collection vocabulary language pair is independent and stored as a BCP 47 tag. Example values:

- `en`, `de`
- `fr`, `es`
- `zh-Hans`, `ja`
