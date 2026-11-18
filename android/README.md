# TrackPet Android demo

Native Kotlin and Jetpack Compose app for Android 12 and newer (minimum API 31, target API 35).
The installable debug APK is in `android/apk/TrackPet-demo.apk`.

## Connect to the backend

1. For a disposable database only (all existing TrackPet data is erased), install the two SQL scripts from `src/config/fresh-install/` in numerical order if you need a fresh development database.
2. Configure PHP using `src/config/.env`. The current connection class reads `SERVER`, `PORT`, `DATABASE`, `DB_USER`, and `PASSWORD`.
3. From the repository root, serve the public directory with `php -S 0.0.0.0:8000 -t public`. Allow the local server through the Windows firewall when prompted.
4. Connect the phone and computer to the same Wi-Fi. In the app's first screen, enter the public root URL, for example `http://192.168.1.10:8000`. An emulator uses `http://10.0.2.2:8000`.
5. Register an account, then log in. Existing website accounts also work. The address is editable on the login screen.

The app shares the backend's cookie session between its screens and detection service. It stores the session cookie locally and obtains the sender identity from the backend. It clears the session on HTTP 401 and stops detection. HTTP is enabled for this local demo.

## Features

- Registration and login with password visibility controls. Registration shows field errors and requires matching passwords before submitting. Tapping outside the registration dialog keeps the draft open.
- Profile editing, password changes, profile photos and account deactivation. Both profile and pet photos accept JPG, PNG, WebP or GIF, up to 5 MiB and 5000 pixels on each side. The app checks the actual image rather than relying on its file name or gallery label.
- Search and pagination for lost reports, report sharing, manual sightings with a map picker and optional phone location.
- Pet registration, editing, photos, deactivation and copying the pet UUID for the collar.
- Open, edit, cancel and close lost reports, including disappearance time and optional initial coordinates.
- Owner-only map and history, latest observation, automatic refresh while the map is visible, and sighting retraction.
- Personal sightings and lost-report history.
- Opt-in automatic BLE detection through a foreground service with a persistent notification and a Pause action.

## Automatic detection

Open Detectar and enable detection once while the app is visible. Grant Nearby devices and precise location permissions. On Android 13+, allow notifications. The service then continues while the app is in the background or the screen is locked.

The scanner matches the existing collar advertisement's complete local name `TrackPet`, then reads its single advertised 128-bit service UUID as the pet ID. It requires no BLE connection or pairing.

For each encounter it resolves the open report, validates the session, obtains a recent phone location, and posts latitude/longitude to `beaconSighting`. The report ID from the lookup is retained through upload. A closed or unknown report never triggers a location request. The server rechecks the lost state when saving.

Repeated packets are grouped. A successful pet detection has a two-minute cooldown, lookup failures and healthy pets have a one-minute cooldown, and uploads are spaced globally. A failed upload gets one short retry with the same request UUID and coordinates. HTTP 429 applies the server's retry delay. The app keeps no queue of stale offline locations.

While detection runs, a session check every ten minutes keeps the active PHP session alive. This request contains no coordinates. Logout or account deactivation stops the service.

Android can stop the process after a force-stop, reboot or battery restriction. Reopen the app to resume enabled detection. This demo starts its location service from a visible activity and does not request unrestricted background location permission. Actual BLE reception, GPS accuracy, screen-lock behavior and battery consumption require testing on the intended phone with the ESP32.

## Build

Open this folder as an Android Studio project, or install an Android SDK with platform 35 and build-tools 35.0.0, set `ANDROID_HOME` or create `local.properties` with `sdk.dir=...`, then run:

```powershell
.\gradlew.bat assembleDebug lintDebug
```

Gradle 8.13 is pinned in the wrapper. Use JDK 17–23. The initial build requires network access. The output is `app/build/outputs/apk/debug/app-debug.apk`. It is signed with a local development key for direct installation.

The source includes the map page, Leaflet distribution and its license in `app/src/main/assets/map/`. Only the base tiles need internet access. If they fail, the map offers **Reintentar**; the coordinate fields and observation history remain available. The tracking view updates markers without resetting the user's zoom.

The optional `.tooling/` folder and `local.properties` are machine-specific and ignored. They are not needed when opening the project with your own Android SDK.

## Verification

The delivered APK passed compilation, Android lint and APK signature verification. Its manifest declares minimum API 31 (Android 12). The Android 12 client test below passed against a disposable PHP/MySQL backend.

The local emulator could not boot on this machine, so device installation, visual layout and actual BLE/GPS/background behavior still require validation on an Android phone.

The instrumentation test `BackendFlowTest` exercises registration, cookie persistence, pet creation, opening a report, BLE upload and idempotent retry, owner tracking, closure rejection and logout against a disposable backend. Pass its URL through the `server` instrumentation argument. It creates records and must run against a test database.

`ApiSessionTest` exercises the client on an Android 12 Robolectric runtime from the computer. Set `TRACKPET_TEST_SERVER` to a disposable backend URL and run `gradlew.bat testDebugUnitTest`. Without this variable the network test is skipped.

`FeedbackFlowsTest` runs without a configured backend. It covers signup validation, the submitted fields, API error details, and profile and pet upload requests and limits.

For a phone retest, start a registration, tap outside it, and try both mismatched and matching passwords. Check the visibility controls at registration and login. Open an owner's map and a manual sighting, verify that streets load and that tapping a point fills in the coordinates. On both photo screens, try a gallery photo that previously failed and check that an oversized or unsupported photo explains what to change.

For a physical collar demonstration, use a helper account on the phone and the owner account on the website. Flash the pet UUID into the ESP32, enable detection, lock the screen and bring the collar nearby. Check that the owner's map receives a BLE sighting. Recover the pet and repeat with a new encounter. The phone should skip location acquisition for the closed search.

Android platform references used for the service behavior are the [foreground service restrictions](https://developer.android.com/develop/background-work/services/fgs/restrictions-bg-start) and [service type requirements](https://developer.android.com/develop/background-work/services/fgs/service-types).
