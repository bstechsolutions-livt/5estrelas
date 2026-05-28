# 5 Estrelas - App Mobile

Wrapper Flutter WebView do sistema 5 Estrelas. Carrega a aplicação web (Laravel + Inertia) dentro de um app nativo, com splash screen, status bar customizada e permissões para uso futuro de câmera, localização e notificações.

## Pré-requisitos

- Flutter 3.41+
- Android SDK (Android Studio ou apenas o SDK + emulador) **ou** dispositivo físico
- Sistema web rodando (Laravel) — por padrão em `http://localhost:8000`

## Instalação inicial

```bash
cd mobile
flutter pub get

# Gera splash screen e ícones a partir de assets/
flutter pub run flutter_native_splash:create
flutter pub run flutter_launcher_icons:main
```

## Rodando em desenvolvimento

### Emulador Android

O emulador acessa o `localhost` do host pelo IP `10.0.2.2`. Como o Laravel está em `http://localhost:8000`, basta rodar:

```bash
flutter run
```

### Celular Android físico

Use o IP da sua máquina na rede local (descubra com `ip addr` ou similar) e passe via `--dart-define`:

```bash
flutter run --dart-define=BACKEND_URL=http://192.168.0.10:8000
```

Certifique-se que o celular está na mesma rede e que o Laravel aceita conexões externas (`php artisan serve --host=0.0.0.0 --port=8000`).

### iOS

```bash
cd ios && pod install && cd ..
flutter run
```

## Estrutura

```
mobile/
├── lib/
│   ├── main.dart                  # Bootstrap, status bar, MaterialApp
│   ├── config/
│   │   └── app_config.dart        # URL, cores, headers
│   └── screens/
│       ├── webview_screen.dart    # WebView principal + back button + pull to refresh
│       └── offline_screen.dart    # Tela "sem conexão"
├── assets/
│   ├── logo.png
│   ├── launcher_icon.png
│   └── splash_logo.png
├── scripts/
│   └── sync-branding.sh           # Stub para sincronizar branding com as settings
├── pubspec.yaml
└── README.md
```

## Branding (sincronizar com as settings do sistema)

O app é um WebView do sistema, mas algumas coisas ficam fixas no APK:
- Nome do app no launcher
- Ícone do app no launcher
- Splash screen (cor de fundo + logo)

Antes de cada build, rode o script para sincronizar:

```bash
./scripts/sync-branding.sh   # ainda não implementado, ver .kiro/steering/mobile-build.md
```

Veja regras completas em `.kiro/steering/mobile-build.md`.

## Build de produção

> ⚠️ Spec de build de produção (assinatura + Play Store + App Store) ainda não foi implementada. Por enquanto, debug builds funcionam normalmente.

Comando básico:

```bash
flutter build apk --release
flutter build ios --release
```

## Permissões declaradas

**Android (`AndroidManifest.xml`):**
- INTERNET, ACCESS_NETWORK_STATE
- CAMERA
- ACCESS_FINE_LOCATION, ACCESS_COARSE_LOCATION, ACCESS_BACKGROUND_LOCATION
- READ_MEDIA_IMAGES, READ_EXTERNAL_STORAGE, WRITE_EXTERNAL_STORAGE
- POST_NOTIFICATIONS
- VIBRATE, WAKE_LOCK

**iOS (`Info.plist`):**
- NSCameraUsageDescription
- NSLocationWhenInUseUsageDescription, NSLocationAlwaysAndWhenInUseUsageDescription
- NSPhotoLibraryUsageDescription, NSPhotoLibraryAddUsageDescription
- NSMicrophoneUsageDescription
- NSAppTransportSecurity (cleartext liberado para dev)

## Identificação no backend

Toda requisição vinda do app envia o header:

```
X-Client: 5estrelas-app
```

E o user-agent contém `5Estrelas/<versão>`. O backend Laravel compartilha em Inertia:

```js
auth.user.is_mobile_app // true quando vem do app
```

(Disponível em `props.is_mobile_app` ou via composable customizado.)
