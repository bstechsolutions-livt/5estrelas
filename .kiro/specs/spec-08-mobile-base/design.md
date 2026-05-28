# Spec 08 - Design

## Estrutura final
```
mobile/
├── android/
├── ios/
├── lib/
│   ├── main.dart                  # Bootstrap do app
│   ├── config/
│   │   └── app_config.dart        # URL backend, cores, identificadores
│   ├── screens/
│   │   ├── webview_screen.dart    # Tela principal com WebView
│   │   └── offline_screen.dart    # Tela quando sem conexão
│   └── widgets/
│       └── (loading_overlay etc)
├── assets/
│   ├── logo.png                   # Logo splash
│   └── launcher_icon.png          # Ícone do app
├── scripts/
│   └── sync-branding.sh           # Stub para implementação futura
├── pubspec.yaml
├── flutter_native_splash.yaml     # Config do splash
├── flutter_launcher_icons.yaml    # Config do ícone
└── README.md
```

## Dependências (pubspec.yaml)
```yaml
dependencies:
  flutter:
    sdk: flutter
  webview_flutter: ^4.10.0          # WebView principal
  webview_flutter_android: ^4.0.0
  webview_flutter_wkwebview: ^3.18.0
  connectivity_plus: ^6.1.0          # Detectar offline
  url_launcher: ^6.3.1               # Abrir links externos
  permission_handler: ^11.3.1        # Permissões runtime
  package_info_plus: ^8.1.1          # Info do app (versão)

dev_dependencies:
  flutter_native_splash: ^2.4.0
  flutter_launcher_icons: ^0.14.0
```

## Configurações

### `lib/config/app_config.dart`
```dart
class AppConfig {
  // URL do backend (configurável por ambiente)
  static const String backendUrl = String.fromEnvironment(
    'BACKEND_URL',
    defaultValue: 'http://10.0.2.2:8000', // emulador Android
  );

  // Header identificador
  static const Map<String, String> defaultHeaders = {
    'X-Client': '5estrelas-app',
  };

  // Branding inicial (substituído por sync-branding.sh no futuro)
  static const String appName = '5 Estrelas';
  static const Color primaryColor = Color(0xFF3B82F6);
  static const Color secondaryColor = Color(0xFF1E1E2D);

  // Domínios considerados "internos" (abrem dentro do WebView)
  static List<String> get internalHosts {
    final uri = Uri.parse(backendUrl);
    return [uri.host, '${uri.host}:${uri.port}'];
  }
}
```

### `flutter_native_splash.yaml`
```yaml
flutter_native_splash:
  color: "#1E1E2D"
  image: assets/logo.png
  android: true
  ios: true
  android_12:
    color: "#1E1E2D"
    image: assets/logo.png
```

### `flutter_launcher_icons.yaml`
```yaml
flutter_launcher_icons:
  android: "launcher_icon"
  ios: true
  image_path: "assets/launcher_icon.png"
  remove_alpha_ios: true
```

## main.dart
```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Status bar
  SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle(
    statusBarColor: AppConfig.secondaryColor,
    statusBarIconBrightness: Brightness.light,
  ));
  runApp(const MyApp());
}
```

## webview_screen.dart - principais funcionalidades

```dart
- WebViewController com headers, user-agent
- onNavigationRequest: redireciona links externos via url_launcher
- onPageFinished: esconde loading
- onWebResourceError: mostra erro
- WillPopScope: handle do back button do Android
- RefreshIndicator: pull to refresh
- Connectivity listener: troca para OfflineScreen quando perde rede
```

## Permissões

### `android/app/src/main/AndroidManifest.xml`
Adicionar antes do `<application>`:
```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION" />
<uses-permission android:name="android.permission.READ_MEDIA_IMAGES" />
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" />
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE"
    android:maxSdkVersion="32" />
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
<uses-permission android:name="android.permission.VIBRATE" />
<uses-permission android:name="android.permission.WAKE_LOCK" />

<uses-feature android:name="android.hardware.camera" android:required="false" />
<uses-feature android:name="android.hardware.location" android:required="false" />
```

Configuração `usesCleartextTraffic="true"` pra dev funcionar com HTTP local.

### `ios/Runner/Info.plist`
Adicionar chaves:
```xml
<key>NSCameraUsageDescription</key>
<string>O app usa a câmera para fotos de fiscalização e perfil.</string>
<key>NSLocationWhenInUseUsageDescription</key>
<string>O app usa sua localização para validar marcação de ponto.</string>
<key>NSLocationAlwaysAndWhenInUseUsageDescription</key>
<string>O app usa sua localização para validar marcação de ponto, mesmo em segundo plano.</string>
<key>NSPhotoLibraryUsageDescription</key>
<string>O app acessa suas fotos para anexar evidências.</string>
<key>NSPhotoLibraryAddUsageDescription</key>
<string>O app salva fotos na galeria.</string>
<key>NSMicrophoneUsageDescription</key>
<string>Microfone pode ser usado em recursos futuros do sistema.</string>
<key>NSAppTransportSecurity</key>
<dict>
    <key>NSAllowsArbitraryLoads</key>
    <true/>
</dict>
```

## Backend - identificação do app

### `app/Http/Middleware/HandleInertiaRequests.php`
Adicionar ao `share()`:
```php
'is_mobile_app' => $request->header('X-Client') === '5estrelas-app',
```

### Useful também: User Agent detection
Se quiser ser mais robusto, pode também checar `str_contains($request->userAgent(), '5Estrelas')`.

## sync-branding.sh (stub)
Conforme regra do steering, criar com placeholder:
```bash
#!/bin/bash
# TODO: implementar sincronização de branding
# Ler settings do banco do Laravel
# Copiar logo/favicon para mobile/assets/
# Atualizar pubspec.yaml e Info.plist
# Rodar flutter_native_splash:create e flutter_launcher_icons:main
echo "TODO: implementar sync-branding"
```

## Como rodar (README do mobile)
```bash
cd mobile

# 1. Instalar dependências
flutter pub get

# 2. Gerar splash e ícone (uma vez ou após mudar branding)
flutter pub run flutter_native_splash:create
flutter pub run flutter_launcher_icons:main

# 3. Rodar no emulador (Laravel rodando em localhost:8000 do host)
flutter run

# Para celular físico, mudar BACKEND_URL para o IP da máquina
flutter run --dart-define=BACKEND_URL=http://192.168.0.10:8000
```

## Considerações de UX
- WebView preenche tela inteira (sem AppBar nativa)
- Loading inicial enquanto WebView monta: indicator centralizado
- Pull to refresh com cor primária
- Tela offline: mensagem amigável + botão de retry + ícone wifi com X
