# Development manual

This manual covers the local development environment, validation, and technical details for contributors to Vocabulary Trainer.

## Prerequisites

- Docker Desktop (or Docker Engine with Compose)
- Visual Studio Code and the **Dev Containers** extension, if using the provided dev container

Copy `.env.example` to `.env` before starting. The values in that file are for local development only; do not use them in production or commit real credentials.

## Start the development environment

### With VS Code Dev Containers

1. Clone the repository and open its folder in VS Code.
2. Copy `.env.example` to `.env`.
3. Run **Dev Containers: Reopen in Container** from the Command Palette.
4. Open [http://localhost:8080](http://localhost:8080) and complete the initial Nextcloud setup using the local credentials in `.env`.
5. Enable the app from a terminal in the repository root:

   ```sh
   docker compose exec -u www-data nextcloud php occ app:enable vocabulary_trainer
   ```

The dev container provides PHP, Composer, and Node.js. Docker Compose starts Nextcloud and MariaDB. The app directory is mounted into Nextcloud's `custom_apps` directory.

## Android client

The Android app is a separate Kotlin/Jetpack Compose client in [`apps/android`](../apps/android/). Android Studio, the Android SDK, and the emulator run on the host; the existing devcontainer remains dedicated to Nextcloud development. See the [Android development guide](../apps/android/README.md) for SDK requirements and emulator networking.

The Android client signs in through Nextcloud Login Flow v2 and uses the versioned JSON API under `/apps/vocabulary_trainer/api/v1`. It supports collection and word-pair management, dashboard and collection statistics, and study sessions. Room caches collections, entries, and statistics for offline reading; writes and study answers require a network connection. The app targets API 37 and supports API 28 and later.

### Without the Dev Container

```sh
cp .env.example .env
docker compose up -d
```

Complete Nextcloud setup at [http://localhost:8080](http://localhost:8080), using the admin username and password from `.env`, then enable the app with the `occ app:enable` command above.

Useful container commands:

```sh
docker compose logs -f nextcloud
docker compose logs -f db
docker compose down
```

`docker compose down -v` also deletes the local Nextcloud and database data volumes. Use it only when you intentionally want to reset the local instance.

## Tests and validation

In VS Code, run **Terminal: Run Task** and choose one of:

- **Vocabulary Trainer: Run tests** — runs the dependency-free PHP service/API tests.
- **Vocabulary Trainer: Validate app** — checks PHP and JavaScript syntax, translation JSON, and runs the PHP tests.

From the repository root, the same checks are:

```sh
php apps/nextcloud/vocabulary_trainer/tests/CollectionServiceTest.php
find apps/nextcloud/vocabulary_trainer -type f -name '*.php' -print0 | xargs -0 -n1 php -l
node --check apps/nextcloud/vocabulary_trainer/js/app.js
php -r 'json_decode(file_get_contents("apps/nextcloud/vocabulary_trainer/l10n/de.json"), true, 512, JSON_THROW_ON_ERROR);'
```

GitHub Actions runs these checks on pushes and pull requests. The workflow can also be started manually from the Actions tab.

The PHP tests use lightweight Nextcloud and database test doubles. They cover collection and word-pair validation, ownership checks, data mapping, writes, and controller response codes.

## App implementation

The installable app is in [`apps/nextcloud/vocabulary_trainer`](../apps/nextcloud/vocabulary_trainer/). Its main parts are:

- `appinfo/routes.php` — app routes
- `lib/Controller/` — Nextcloud request handling, including learning-session endpoints
- `lib/Service/CollectionService.php` — collection and word-pair data operations
- `lib/Service/LearningService.php` — session generation, answer checking, and progress scheduling
- `lib/Migration/` — versioned database schema migrations
- `templates/`, `js/`, and `css/` — user interface

The initial migration creates the collections, entries, progress, and user-settings tables. Later migrations add settings such as the configurable answer-display delay. Migrations do not seed user data or alter schema during requests.

## API reference

The web UI uses the existing routes under `/apps/vocabulary_trainer`. These routes retain Nextcloud request-token/CSRF protection for writes.

The Android client uses the separate, versioned JSON API under `/apps/vocabulary_trainer/api/v1`. Authenticate with the app password created by Nextcloud Login Flow v2 using HTTP Basic authentication. Every request must also include `OCS-APIRequest: true` and `X-Vocabulary-Trainer-API: 1`. The custom header is required because API actions are exempt from browser CSRF-token validation; cross-origin requests are not enabled. Nextcloud's authenticated session supplies the user identity; request bodies must never supply an owner UID.

JSON writes return the same response shapes and validation messages as the corresponding web operation. API errors use `{"error":"..."}` with HTTP 400 for invalid input, 401 for a missing authenticated mobile request, and 404 for a missing or unowned resource. Successful creates return HTTP 201; other successful writes return HTTP 200.

### Version 1 mobile routes

| Method | Route | Request / response |
| --- | --- | --- |
| GET | `/apps/vocabulary_trainer/api/v1/collections` | List owned collections, pair counts, and normalized learning progress |
| GET | `/apps/vocabulary_trainer/api/v1/collections/dashboard/statistics` | Overall word-pair count, accuracy, progress, and pairs practiced today |
| POST | `/apps/vocabulary_trainer/api/v1/collections` | JSON: `name`, `sourceLanguage`, `targetLanguage`, `boxCount`; returns `{"success":true}` |
| PUT | `/apps/vocabulary_trainer/api/v1/collections/{id}` | Same JSON fields as create; returns `{"success":true}` |
| DELETE | `/apps/vocabulary_trainer/api/v1/collections/{id}` | Delete an owned collection; returns `{"success":true}` |
| GET | `/apps/vocabulary_trainer/api/v1/collections/{collectionId}/entries` | List owned collection word pairs (`id`, `sourceTerm`, `targetTerm`, `notes`) |
| GET | `/apps/vocabulary_trainer/api/v1/collections/{collectionId}/statistics` | Per-direction box counts, accuracy, and normalized progress |
| POST | `/apps/vocabulary_trainer/api/v1/collections/{collectionId}/entries` | JSON: `sourceTerm`, `targetTerm`, optional `notes`; returns `{"success":true}` |
| PUT | `/apps/vocabulary_trainer/api/v1/collections/{collectionId}/entries/{entryId}` | Same JSON fields as entry create; returns `{"success":true}` |
| DELETE | `/apps/vocabulary_trainer/api/v1/collections/{collectionId}/entries/{entryId}` | Delete one owned word pair; returns `{"success":true}` |
| DELETE | `/apps/vocabulary_trainer/api/v1/collections/{collectionId}/entries` | JSON: `entryIds` array; returns `{"deleted":count}` |
| POST | `/apps/vocabulary_trainer/api/v1/collections/{collectionId}/entries/move` | JSON: `entryIds` array and `targetCollectionId`; returns `{"moved":count}` |
| GET | `/apps/vocabulary_trainer/api/v1/collections/{collectionId}/study/settings` | Read the user's study preferences |
| POST | `/apps/vocabulary_trainer/api/v1/collections/{collectionId}/study/session` | Start a session; see the session fields below |
| POST | `/apps/vocabulary_trainer/api/v1/collections/{collectionId}/study/entries/{entryId}/answer` | JSON: `direction`, `answer`; grades and records progress |

A study-session request accepts `direction`, `mode`, and `selection`; selection-specific fields are `box`, `errorRate`, or `days`. It may also include `startWithNew`, `includeFinalBox`, `answerDelaySeconds`, and `questionTimeLimitSeconds`. The server validates the values, persists supported user preferences, applies the same scheduler as the web client, and returns questions without revealing expected answers before submission. An answer request returns correctness, the correct answer, the resulting box, and answer counters.

The versioned API uses the same service-level validation and owner scoping as the web routes. For collection and study behavior details, see the route descriptions below and the [project specification](project-spec.md).

### Collections

| Method | Route | Action |
| --- | --- | --- |
| GET | `/collections` | List the signed-in user's collections, entry counts, and normalized learning progress |
| GET | `/collections/dashboard/statistics` | Return overall word-pair count, accuracy, learning progress, and pairs practiced today |
| POST | `/collections` | Create a collection |
| PUT | `/collections/{id}` | Update a collection |
| DELETE | `/collections/{id}` | Delete an owned collection and its dependent entries |

Collection create/update validates names, language tags, distinct source and target languages, and the 2–10 study-box range.

Dashboard statistics aggregate only the signed-in user's collections. Learning progress includes both directions for every pair, with unstarted directions at box 1; answer accuracy combines all recorded answers. Pairs practiced today count unique pairs with an answer recorded since midnight UTC.

### Word pairs

| Method | Route | Action |
| --- | --- | --- |
| GET | `/collections/{collectionId}/entries` | List a collection's word pairs |
| GET | `/collections/{collectionId}/statistics` | Return per-direction box distributions and collection learning statistics |
| POST | `/collections/{collectionId}/entries` | Create a word pair |
| PUT | `/collections/{collectionId}/entries/{entryId}` | Update a word pair |
| DELETE | `/collections/{collectionId}/entries/{entryId}` | Delete one word pair |
| DELETE | `/collections/{collectionId}/entries` | Delete selected word pairs; `entryIds` is a JSON-encoded list |
| POST | `/collections/{collectionId}/entries/move` | Move selected word pairs; `entryIds` is a JSON-encoded list and `targetCollectionId` is the destination |

Entry operations verify ownership of the source collection and, for moves, the destination collection. Bulk operations accept 1–500 unique IDs and verify that every selected pair belongs to the source collection before changing data.

Collection statistics report answer accuracy across both directions and normalized average box advancement across every pair-direction (new progress is treated as box 1). Box distributions remain separate by translation direction because a pair can occupy different boxes in each direction.

### Learning

| Method | Route | Action |
| --- | --- | --- |
| GET | `/collections/{collectionId}/study/settings` | Read the user's learning preferences |
| POST | `/collections/{collectionId}/study/session` | Start a session from a selection and mode |
| POST | `/collections/{collectionId}/study/entries/{entryId}/answer` | Check an answer and update progress |

Learning progress is per user, word pair, and translation direction. Typed answers are case-insensitive and ignore leading/trailing and repeated whitespace. Multiple-choice and mixed modes require more than 10 matching pairs; choices are drawn from the collection in the answer language. Mixed mode randomly chooses a question type for each pair. The user's answer-display delay is configurable from 1 to 30 seconds (default 5), after which the next question is shown automatically. An optional response time limit is configurable from 1 to 300 seconds; zero disables it. The pin control pauses and resumes whichever timer is active. When a question is submitted without an answer (including on timeout), it is recorded as incorrect; a non-empty answer is checked normally. Clicking outside interactive answer controls on the study card submits the current answer early or advances from feedback.

Automatic sessions include new pairs and due reviews. The user's `start_with_new` preference puts new pairs first; when no reviews are due, eligible not-yet-due reviews are added after the new pairs. Otherwise, not-yet-due reviews are left for a later session. If the preference is disabled, new and due pairs are mixed. Correct answers advance one box; incorrect answers reset to box 1. Review intervals are 1, 3, 7, 15, 30, 60, 120, 240, and 480 days for boxes 2–10. The collection's final box is excluded from automatic sessions unless `include_final_box` is enabled. If enabled but no due timestamp exists, its due date is calculated from the last answer.

Targeted sessions can select new pairs, one box, pairs whose lifetime error percentage in that direction is above a threshold, or pairs not practiced for a specified number of days. Each matching pair is asked once per session.

## Product direction

Vocabulary Trainer is developed as a Nextcloud app with a separate Android client. Keep the versioned API and learning model stable for both clients.

- Interface languages: English and German.
- Vocabulary languages are user-defined per collection, stored as BCP 47 tags.
- Collections are private by default, and all collection operations must be scoped to the authenticated user.
- Study modes include multiple choice and typed answers in either translation direction; mixed mode selects randomly per question.
- Progress is tracked per user, word pair, and direction. Correct answers move an entry up one box; incorrect answers return it to the first box.
- Box count is configurable per collection.
- Sharing and import/export may be future extensions.

See the [project specification](project-spec.md), [architecture](architecture.md), and [database schema](database-schema.md) for additional design details.

## Data removal

Nextcloud removes an app's files when it is removed, but it does not automatically drop app-owned database tables. In Nextcloud 35, registered uninstall repair steps also run when an app is disabled, so they cannot safely delete user data only on permanent removal. To preserve data when the app is temporarily disabled, this app does not register a destructive uninstall step. Removing the app therefore leaves its database tables in place until a safe uninstall-only cleanup mechanism is available.
