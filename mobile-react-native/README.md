# Younz Digital Center — React Native Android

This is the React Native/Expo mobile client for the existing Laravel API. It is intentionally separate from the existing Flutter application in `../mobile`.

## Local setup

```powershell
cd mobile-react-native
Copy-Item .env.example .env
npm install --ignore-scripts
npm run typecheck
npx expo start
```

Use `http://10.0.2.2:8080` for an Android emulator when Laravel is listening on the host at port 8080. For a physical device, set `EXPO_PUBLIC_API_URL` in `.env` to the computer's LAN IP, for example `http://192.168.1.20:8080`. Use HTTPS in production.

## Included flows

- Customer login, home summary, digital product/top-up catalog, top-up checkout handoff, service-order portal, and logout.
- Admin/staff login with access code and 6-digit 2FA, dashboard, digital transaction list, service-order list, daily report, and logout.
- Access tokens are stored in Expo SecureStore. Provider credentials and PPOB agent/operator tokens are never bundled into the app.

## Build Android

For development, use Expo Go or a local Android emulator. For a signed release, configure the Android application identifier in `app.json`, then use the project's EAS or local Android build process after the API URL and signing credentials are finalized.
