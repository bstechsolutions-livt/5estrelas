---
inclusion: always
---

# Regras de Build do App Mobile (Flutter)

O app mobile é um wrapper WebView do sistema web. Algumas configurações ficam **fixas no APK/IPA** (não dá pra mudar em runtime). Por isso, antes de qualquer build (debug, release, produção), o branding precisa ser **sincronizado com as settings atuais do sistema**.

## O que precisa ser sincronizado antes do build

| Recurso | Origem (settings do sistema) | Destino (Flutter) |
|---------|------------------------------|-------------------|
| Nome do app | `app_name` | `pubspec.yaml`, `AndroidManifest.xml`, `Info.plist` |
| Ícone do app | `favicon_path` ou `logo_path` | `mobile/assets/launcher_icon.png` (gera ícones via `flutter_launcher_icons`) |
| Logo do splash | `logo_path` | `mobile/assets/splash_logo.png` |
| Cor da splash | `secondary_color` | `mobile/native_splash.yaml` (gera via `flutter_native_splash`) |
| Cor da status bar | `secondary_color` | Lido do `app_config.dart` |
| Cor primária | `primary_color` | Lido do `app_config.dart` |

## Comando padrão de build

Sempre que o usuário pedir "buildar o app" ou "gerar APK", o agente DEVE:

1. **Ler as settings do banco** (via Artisan ou query direta):
   ```bash
   php artisan tinker --execute="echo json_encode([
       'app_name' => \App\Models\Setting::get('app_name'),
       'primary_color' => \App\Models\Setting::get('primary_color'),
       'secondary_color' => \App\Models\Setting::get('secondary_color'),
       'logo_path' => \App\Models\Setting::get('logo_path'),
       'favicon_path' => \App\Models\Setting::get('favicon_path'),
   ]);"
   ```

2. **Copiar imagens** do `app/storage/app/public/` para `mobile/assets/`:
   - Logo → `mobile/assets/splash_logo.png`
   - Favicon (ou logo se favicon não existir) → `mobile/assets/launcher_icon.png`

3. **Atualizar configs**:
   - `mobile/pubspec.yaml`: campo `name` (slug) e ícone
   - `mobile/lib/config/app_config.dart`: cores e URL
   - `mobile/flutter_native_splash.yaml`: cor de fundo
   - `mobile/flutter_launcher_icons.yaml`: caminho do ícone

4. **Rodar geradores**:
   ```bash
   cd mobile && flutter pub get
   flutter pub run flutter_native_splash:create
   flutter pub run flutter_launcher_icons:main
   ```

5. **Renomear app/package** (se necessário, via `package_rename` ou script):
   - Android: app name no `android/app/src/main/AndroidManifest.xml`
   - iOS: `CFBundleDisplayName` no `ios/Runner/Info.plist`

6. **Buildar**:
   ```bash
   flutter build apk --release    # Android
   flutter build ios --release    # iOS
   ```

## Script de sincronização

Para automatizar, manter um script `mobile/scripts/sync-branding.sh` (ou Dart) que executa todos os passos 1-5 sozinho. Build manual depois apenas chama o script + `flutter build`.

Estado ideal: `cd mobile && ./scripts/sync-branding.sh && flutter build apk --release`

## O que NÃO precisa ser sincronizado

Tudo que está dentro do WebView (cores, layout, conteúdo) já é dinâmico — vem direto do sistema web.

- Tela de login customizada → vem do sistema
- Cores do header e sidebar → vêm do sistema
- Logo no header → vem do sistema
- Conteúdo de notícias, destaques, etc → tudo do sistema

Apenas a "casca" do app (ícone no launcher, nome do app, splash inicial antes do WebView carregar) precisa de sync.

## Observações

- **Reposicionar branding em produção**: para republicar o app com cores/logos novos, é necessário um novo build + republicação na Play Store / App Store.
- **Multi-cliente futuro**: quando o sistema for white-label real (vendido para outros clientes), cada cliente terá seu próprio build com seu próprio nome, ícone, package name e URL apontando para sua instância.
- **Versionamento**: a cada sync de branding, incrementar `versionCode` (Android) e `CFBundleVersion` (iOS) automaticamente.

## Fluxo recomendado

```
1. Cliente sobe nova logo no painel /settings/aparencia
2. Dev (ou pipeline CI) executa: cd mobile && ./scripts/sync-branding.sh
3. Dev gera build: flutter build apk --release
4. Dev/CI publica APK no Play Store (ou distribui como pacote)
```

## Implementação prática

A primeira versão da Spec 08 (mobile base) deve incluir:
- Estrutura básica do projeto Flutter
- WebView funcional
- `app_config.dart` lendo cores
- Splash screen
- Stub do script `sync-branding.sh` (mesmo que vazio inicialmente, com TODO marcado)

Numa spec futura dedicada de "build pipeline", implementar o script completo e o fluxo de publicação.


---

## Push Notifications (Firebase Cloud Messaging)

A estrutura base de push está implementada e aguarda os arquivos de configuração do cliente para ativar.

### O que já está pronto (commit anterior)

**Backend (Laravel)**:
- Tabela `device_tokens` (user_id, token, platform, device_name, app_version, last_seen_at)
- `App\Models\DeviceToken`
- `App\Http\Controllers\DeviceTokenController` com endpoints:
  - `POST /device-tokens` (registrar)
  - `DELETE /device-tokens` (remover)
- `App\Services\FcmService` (stub) — chamado automaticamente pelo `NotificationService::send()`
- Env `FCM_ENABLED=false` (default). Mude pra `true` quando configurado.

**Flutter (mobile/)**:
- Dependências: `firebase_core`, `firebase_messaging`, `flutter_local_notifications`
- `lib/services/push_service.dart` — inicialização condicional (silencioso se Firebase não configurado)
- `lib/main.dart` chama `PushService().init()` antes do `runApp()`
- WebView registra o token automaticamente após login (lê cookie de sessão e bate em `/device-tokens`)

### Como ativar push (quando cliente entregar os arquivos)

#### Android

1. Cliente baixa `google-services.json` do Firebase Console (app cadastrado com package `com.bstechsolutions.cinco_estrelas`)
2. Coloca em `mobile/android/app/google-services.json`
3. Edita `mobile/android/build.gradle` ou `mobile/android/settings.gradle.kts` adicionando o plugin do Google Services:
   ```gradle
   plugins {
       id "com.google.gms.google-services" version "4.4.2" apply false
   }
   ```
4. Em `mobile/android/app/build.gradle.kts`:
   ```gradle
   apply plugin: "com.google.gms.google-services"
   ```
5. Roda `cd mobile && flutter clean && flutter pub get && flutter run`

#### iOS

1. Cliente baixa `GoogleService-Info.plist` do Firebase Console
2. Coloca em `mobile/ios/Runner/GoogleService-Info.plist`
3. Em `mobile/ios/Runner/AppDelegate.swift`, adiciona:
   ```swift
   import FirebaseCore
   FirebaseApp.configure()
   ```
4. Cria APNs Authentication Key em developer.apple.com → Keys → "+" → APNs
5. Sobe o `.p8` no Firebase Console → Cloud Messaging → Apple app config

#### Backend (FCM Admin SDK)

1. No Firebase Console → Project Settings → Service Accounts → Generate new private key
2. Salva o JSON em `app/storage/app/private/firebase/service-account.json` (NÃO commitar)
3. `composer require kreait/laravel-firebase`
4. No `.env`:
   ```
   FCM_ENABLED=true
   FIREBASE_CREDENTIALS=storage/app/private/firebase/service-account.json
   ```
5. Atualiza `App\Services\FcmService::sendToTokens()` com a implementação real (já tem TODO no código)

---

## Shorebird (Code Push)

Estrutura preparada para receber Shorebird quando o cliente quiser configurar.

### O que já está pronto

- `pubspec.yaml` com `version: 1.0.0+1` (versionamento padrão Flutter)
- Stub de `mobile/scripts/sync-branding.sh` documentado pra integrar shorebird depois

### Como ativar Shorebird (passos manuais)

1. Criar conta em [shorebird.dev](https://shorebird.dev)
2. Instalar CLI:
   ```bash
   curl --proto '=https' --tlsv1.2 https://raw.githubusercontent.com/shorebirdtech/install/main/install.sh -sSf | sh
   ```
3. `shorebird login`
4. No projeto:
   ```bash
   cd mobile
   shorebird init
   ```
5. Isso cria `mobile/shorebird.yaml`. NÃO commitar credenciais.

### Fluxo de release

Substitui `flutter build apk` por:

```bash
# Primeira vez (release "completa"): publicar na Play Store
shorebird release android

# Próximas vezes (apenas mudanças Dart, sem build novo na store):
shorebird patch android
```

iOS é análogo: `shorebird release ios` / `shorebird patch ios`.

### Limitações

- Só funciona com builds release (não debug)
- Patches só funcionam pra mudanças em código Dart (não muda nativo, AndroidManifest, recursos)
- Pra mudar branding (cores, logos, nome) continua precisando de release completa + publicação na store

---

## Resumo do estado atual

| Item | Status |
|------|--------|
| WebView wrapping do sistema web | ✅ Pronto |
| Splash screen + ícone | ✅ Pronto |
| Picker de arquivo (câmera/galeria/arquivos) | ✅ Pronto |
| Download de arquivos via WebView | ✅ Pronto (via dio + cookies) |
| Notificações in-app (sino) | ✅ Pronto (web) |
| Tempo real (WebSocket Reverb) | ✅ Pronto |
| Push Firebase | 🟡 Estrutura pronta, aguarda google-services.json |
| Shorebird code push | 🟡 Estrutura pronta, aguarda conta + init |
| Sync de branding antes do build | ⏳ Stub. Implementar em spec dedicada |
