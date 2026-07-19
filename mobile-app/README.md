# SportsUniverse Mobile

Cross-platform Expo/React Native client for Android and iOS. It consumes the Laravel `/api/v1` backend and follows the supplied SportsUniverse interface.

## Included

- Expo Router native navigation
- Android package and iOS bundle identifier: `com.sportuniverse.mobile`
- Secure Sanctum token storage with Expo SecureStore
- Axios API client with Android-emulator and iOS-simulator defaults
- Zustand authentication state
- TanStack Query server state
- Native login and two-step role registration
- Full-screen vertical sports feed
- Two-video guest preview followed by an authentication gate
- Protected engagement actions
- Native tab navigation and authenticated profile/logout
- Shared SportsUniverse brand tokens

## Requirements

- Node.js 20.19.4 or newer (required by Expo SDK 57)
- Android Studio for the Android emulator
- Xcode on macOS for the iOS simulator
- Expo Go or a development build on physical devices

## Configure the API

Copy `.env.example` to `.env` and set a URL reachable by the device:

```env
EXPO_PUBLIC_API_URL=http://192.168.1.100:8000/api/v1
```

Defaults without `.env`:

- Android emulator: `http://10.0.2.2:8000/api/v1`
- iOS simulator: `http://localhost:8000/api/v1`

## Run

```bash
npm install
npm run android
npm run ios
```

The live video feature uses native WebRTC and requires an Expo development build; it does not run in Expo Go:

```bash
npx expo run:ios
# or
npx expo run:android
```

Copy `.env.example` to `.env`, use the computer's LAN IP for both the API and Reverb host, and make sure the Laravel server and Reverb are reachable from the device. Start Reverb once with:

```bash
php artisan reverb:start --debug
```

Do not start a second Reverb process on port 8080. For local HTTP/WebSocket development, use `http` and `ws`; unsupported SSL requests mean the client is attempting `https`/`wss` against the local non-TLS server.

For store-ready builds:

```bash
npx eas build --platform android
npx eas build --platform ios
```

For a directly installable Android APK:

```bash
npm run build:apk
```

The `preview` EAS profile produces an internally distributed APK configured for the public HTTPS API. Do not replace the HTTPS endpoint with plain HTTP for a downloadable build because authentication tokens and user data must be encrypted in transit.

## Design tokens

- Primary `#476FEA`
- Sport Cyan `#86C0ED`
- Success `#77A571`
- Opportunity `#E2B344`
- Women in Sport `#B96B9D`
- Deep Navy `#1B212D`
- Agent `#6565C7`
