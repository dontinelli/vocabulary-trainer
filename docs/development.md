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
- `lib/Controller/` — Nextcloud request handling
- `lib/Service/CollectionService.php` — collection and word-pair data operations
- `lib/Migration/` — versioned database schema migrations
- `templates/`, `js/`, and `css/` — user interface

The initial migration creates the collections, entries, progress, and user-settings tables. It does not seed user data or alter schema during requests. Apply schema changes through versioned migrations.

## API reference

App routes use the `/apps/vocabulary_trainer` prefix. The signed-in Nextcloud session determines the owner; user IDs are not accepted from request data. Write requests use Nextcloud request-token/CSRF protection.

### Collections

| Method | Route | Action |
| --- | --- | --- |
| GET | `/collections` | List the signed-in user's collections and entry counts |
| POST | `/collections` | Create a collection |
| PUT | `/collections/{id}` | Update a collection |
| DELETE | `/collections/{id}` | Delete an owned collection and its dependent entries |

Collection create/update validates names, language tags, distinct source and target languages, and the 2–10 study-box range.

### Word pairs

| Method | Route | Action |
| --- | --- | --- |
| GET | `/collections/{collectionId}/entries` | List a collection's word pairs |
| POST | `/collections/{collectionId}/entries` | Create a word pair |
| PUT | `/collections/{collectionId}/entries/{entryId}` | Update a word pair |
| DELETE | `/collections/{collectionId}/entries/{entryId}` | Delete one word pair |
| DELETE | `/collections/{collectionId}/entries` | Delete selected word pairs; `entryIds` is a JSON-encoded list |
| POST | `/collections/{collectionId}/entries/move` | Move selected word pairs; `entryIds` is a JSON-encoded list and `targetCollectionId` is the destination |

Entry operations verify ownership of the source collection and, for moves, the destination collection. Bulk operations accept 1–500 unique IDs and verify that every selected pair belongs to the source collection before changing data.

## Product direction

Vocabulary Trainer is being developed as a Nextcloud app first, with a possible Android client later. Keep the API and learning model suitable for that future client.

- Interface languages: English and German.
- Vocabulary languages are user-defined per collection, stored as BCP 47 tags.
- Collections are private by default, and all collection operations must be scoped to the authenticated user.
- Planned study modes include multiple choice and typed answers in either translation direction.
- Planned progress is tracked per user, word pair, and direction. Correct answers move an entry up one box; incorrect answers return it to the first box.
- Box count is configurable per collection.
- Sharing and import/export may be future extensions.

See the [project specification](project-spec.md), [architecture](architecture.md), and [database schema](database-schema.md) for additional design details.

## Data removal

Nextcloud removes an app's files when it is removed, but it does not automatically drop app-owned database tables. In Nextcloud 35, registered uninstall repair steps also run when an app is disabled, so they cannot safely delete user data only on permanent removal. To preserve data when the app is temporarily disabled, this app does not register a destructive uninstall step. Removing the app therefore leaves its database tables in place until a safe uninstall-only cleanup mechanism is available.
