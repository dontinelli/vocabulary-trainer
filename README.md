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

## Project direction

- First release interface languages: English and German.
- Users choose the source and target vocabulary languages when creating each collection; vocabulary languages are not limited to English and German.
- Collections are private by default.
- Vocabulary entries can be created, edited, and removed.
- Study modes: multiple choice or typed answer, in either translation direction.
- Learning progress is tracked per user, entry, and direction. A correct answer moves an entry up one box; an incorrect answer returns it to the first box. The number of boxes should be configurable.
- Sharing and import/export are possible future extensions.

The repository is being set up for a Nextcloud app first, with an Android client as a later step. The API and learning model should be designed to support that second client.
