# Vocabulary Trainer

Application to learn vocabulary.

## Local development

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker Engine with Compose)
- [Visual Studio Code](https://code.visualstudio.com/)
- The VS Code **Dev Containers** extension

Copy `.env.example` to `.env` before starting. The values in that file are for local development only; do not use them in production or commit real credentials.

### Start in VS Code

1. Clone this repository and open its folder in VS Code.
2. Copy `.env.example` to `.env`.
3. Run **Dev Containers: Reopen in Container** from the Command Palette.
4. Open [http://localhost:8080](http://localhost:8080) and complete the initial Nextcloud setup using the local credentials in `.env`.

The dev container provides PHP, Composer, and Node.js. Docker Compose starts Nextcloud and MariaDB. The app work directory, `apps/nextcloud/vocabulary_trainer`, is mounted into Nextcloud's `custom_apps` directory. After completing Nextcloud's initial setup, enable the app from the repository root:

```sh
docker compose exec -u www-data nextcloud php occ app:enable vocabulary_trainer
```

The app appears in the Nextcloud navigation menu. Its collection interface saves each user's collections in the Nextcloud database; collection entries and study functionality are still under development.

### Start without the Dev Container

You can also run the Nextcloud and database services directly:

```sh
cp .env.example .env
docker compose up -d
```

Then finish Nextcloud's initial setup at [http://localhost:8080](http://localhost:8080), using the admin username and password from `.env`.

### Useful commands

```sh
docker compose logs -f nextcloud
docker compose logs -f db
docker compose down
```

`docker compose down -v` also deletes the local Nextcloud and database data volumes. Use it only when you intentionally want to reset the local instance.

### Tests and validation

In VS Code, run **Terminal: Run Task** and choose **Vocabulary Trainer: Run tests** or **Vocabulary Trainer: Validate app**. The test task runs the app's dependency-free PHP tests; the validation task also checks PHP and JavaScript syntax and translation JSON.

GitHub Actions runs the same checks on pushes and pull requests. The workflow can also be started manually from the Actions tab.

To run the checks from a terminal in the repository root:

```sh
php apps/nextcloud/vocabulary_trainer/tests/CollectionServiceTest.php
find apps/nextcloud/vocabulary_trainer -type f -name '*.php' -print0 | xargs -0 -n1 php -l
node --check apps/nextcloud/vocabulary_trainer/js/app.js
php -r 'json_decode(file_get_contents("apps/nextcloud/vocabulary_trainer/l10n/de.json"), true, 512, JSON_THROW_ON_ERROR);'
```

## Project direction

- First release interface languages: English and German.
- Users choose the source and target vocabulary languages when creating each collection; vocabulary languages are not limited to English and German.
- Collections are private by default.
- Vocabulary entries can be created, edited, and removed.
- Study modes: multiple choice or typed answer, in either translation direction.
- Learning progress is tracked per user, entry, and direction. A correct answer moves an entry up one box; an incorrect answer returns it to the first box. The number of boxes should be configurable.
- Sharing and import/export are possible future extensions.

The repository is being set up for a Nextcloud app first, with an Android client as a later step. The API and learning model should be designed to support that second client.
