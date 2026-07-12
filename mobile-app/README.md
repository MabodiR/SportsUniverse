# SportUniverse Mobile

Cross-platform Expo/React Native client for Android and iOS. It consumes the Laravel `/api/v1` backend and follows the supplied SportUniverse interface.

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
- Shared SportUniverse brand tokens

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

For store-ready builds:

```bash
npx eas build --platform android
npx eas build --platform ios
```

## Design tokens

- Deep Navy `#0D1B2A`
- Universe Blue `#1B63F3`
- Community Pink `#E646A2`
- Opportunity Orange `#FFB020`
- Growth Green `#18B26B`
