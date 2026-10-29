# SportsUniverse cross-platform delivery framework

## Architecture

| Layer | Responsibility | Location |
| --- | --- | --- |
| Domain/API | Rules, validation, permissions, persistence, vouchers, subscriptions and API resources | `app/`, `routes/api/`, `database/` |
| Web | Vue/Inertia browser experience | `resources/js/` |
| Mobile | Expo React Native experience shared by iOS and Android | `mobile-app/` |
| Operations | CI, xneelo web deployment and Expo releases | `.github/workflows/` |

React Native produces the iOS and Android applications from one source tree. Huawei devices can use the Android build when features do not require unavailable Google Mobile Services. AppGallery publication is a distinct release channel and must be tested on a Huawei device or emulator.

## Definition of done

A cross-platform change is complete only when:

- Shared rules live in Laravel and the mobile API contract is versioned.
- The web and mobile experiences are implemented or a platform exception is documented.
- Authorization, validation, loading, empty, error and offline behavior have been considered.
- Backend tests, web production build and mobile typecheck pass.
- Migrations are forward-safe and production data is protected.
- Release notes identify whether the change needs web deploy, EAS Update, new native binaries, or AppGallery submission.

## Parity gate

`scripts/check-platform-parity.sh` compares changed user-facing web and mobile files. A one-sided product change fails CI unless the pull request or commit message includes one of these markers with a reason:

- `[platform-web-only: reason]`
- `[platform-mobile-only: reason]`

Do not use an override merely to avoid implementing the other client.

## Git-to-production flow

1. Push a feature branch and open a pull request.
2. CI installs PHP, web and mobile dependencies; builds web; typechecks mobile; migrates a disposable database; and runs tests.
3. Merge only after CI passes and review is complete.
4. Successful CI on `main` triggers `.github/workflows/deploy.yml`, which backs up PostgreSQL, deploys to xneelo, migrates, restarts services and checks health.
5. If `mobile-app/` changed, `.github/workflows/mobile-release.yml` publishes an EAS production update. Native dependency or configuration changes are flagged for a new binary build.

Required GitHub configuration:

- Protect `main` and require the `CI / build-test` check.
- Configure the `production` environment and its approval policy.
- Store `EXPO_TOKEN` as a GitHub Actions secret for mobile releases.
- Keep the self-hosted xneelo runner online with labels `self-hosted`, `Linux`, `X64`, `xneelo-production`.

## Mobile distribution

- Apple App Store: EAS Build and EAS Submit using the production profile.
- Google Play: EAS Build and EAS Submit using the production profile.
- Huawei AppGallery: build/sign the Android AAB or APK, run Huawei compatibility checks, then submit through AppGallery Connect. Replace or provide fallbacks for Google-only services before claiming Huawei support.
