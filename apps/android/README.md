# Vocabulary Trainer for Android

The Android client is a separate Kotlin/Jetpack Compose app that connects to the Nextcloud app. It supports Nextcloud Login Flow v2, collection and word-pair management, statistics, and the web app's study modes and selections.

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
- The client uses the versioned API under `/apps/vocabulary_trainer/api/v1` for collections, word pairs, statistics, study settings, sessions, and answers. All requests use the Nextcloud app password and required API headers.
- Room caches collections, word pairs, and statistics; the cache remains visible when refresh fails. Mutations and study answers are online-only.
- Collection details filter word pairs by study box and translation direction. Typed study answers submit from the keyboard's Done/Enter action; active study timers display as a pausable progress bar.
- Sign-out clears the local cache. Existing schema-version-1 installations are migrated to the entry/statistics cache schema.
- Retrofit/OkHttp requests and database refreshes run off the main thread.

The versioned API and the Nextcloud development environment are described in the [development manual](../../docs/development.md).
