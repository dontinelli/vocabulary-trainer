# Vocabulary Trainer for Android

The Android client is a separate Kotlin/Jetpack Compose app that connects to the Nextcloud app. It currently supports Nextcloud Login Flow v2, collection listing, refresh, and a local Room cache.

## Development setup

- Android Studio with the Android SDK and an emulator or Android device
- Compile/target SDK 37; minimum supported SDK 28
- Use the Gradle wrapper included in this project

Open `apps/android` in Android Studio, sync Gradle, and run the `app` debug configuration. From the project directory, `./gradlew assembleDebug` builds the debug APK.

## Connecting to local Nextcloud

The local Nextcloud development instance is exposed on host port 8080. For the standard Android emulator, the host is usually reachable at `http://10.0.2.2:8080`. If the app cannot connect through that alias, use the host machine's LAN address instead, for example `http://192.0.2.10:8080`.

Nextcloud must trust the host and port used by the emulator. For a local Compose instance, add the chosen address at an unused trusted-domain index, for example:

```sh
docker compose exec -u www-data nextcloud php occ config:system:set trusted_domains 2 --value=192.0.2.10:8080
```

Replace the example address with the actual host IP. The app allows cleartext HTTP only in its debug manifest for local development; release builds use Android's HTTPS-only default.

## Current client contract

- Sign-in uses Nextcloud Login Flow v2 in the system browser. The generated app password is stored using Android encrypted preferences and sent with authenticated API requests.
- Collections are fetched from `GET /apps/vocabulary_trainer/api/v1/collections`.
- The app caches collection summaries locally in Room and displays the cache when refresh fails.
- Retrofit/OkHttp requests and database refreshes run off the main thread.

The versioned API and the Nextcloud development environment are described in the [development manual](../../docs/development.md).
