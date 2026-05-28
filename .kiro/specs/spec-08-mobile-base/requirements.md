# Spec 08 - App Mobile (Flutter WebView)

## Objetivo
Criar a base do app mobile usando Flutter como wrapper WebView do sistema web. App deve carregar a aplicação Laravel/Inertia e exibir como um app nativo, com splash screen, status bar customizada, controles de navegação nativa e permissões necessárias para módulos futuros (câmera, localização, notificações, armazenamento).

## Requisitos

### R1: Estrutura do projeto
- Pasta `mobile/` na raiz do workspace
- Projeto Flutter padrão com Android e iOS
- README explicando como rodar

### R2: WebView
- Pacote: `webview_flutter` (oficial)
- URL configurável em `lib/config/app_config.dart`
- URL padrão dev: `http://10.0.2.2:8000` (emulador Android)
- Cookies persistentes (login não some ao fechar)
- JavaScript habilitado
- Identificação no header HTTP: `X-Client: 5estrelas-app`
- User-agent customizado: `5Estrelas/{versão} (Android/iOS)`

### R3: Splash screen
- `flutter_native_splash` configurado
- Cor de fundo: cor secundária do sistema (placeholder por enquanto)
- Logo centralizado
- Some quando o WebView carrega

### R4: Status bar
- Cor de fundo: cor secundária do sistema
- Texto da status bar: claro ou escuro automaticamente baseado no contraste

### R5: Permissões no AndroidManifest e Info.plist
**Android:**
- INTERNET
- ACCESS_NETWORK_STATE
- CAMERA
- ACCESS_FINE_LOCATION + ACCESS_COARSE_LOCATION
- ACCESS_BACKGROUND_LOCATION (opcional, pra ponto futuro)
- READ_MEDIA_IMAGES, READ_EXTERNAL_STORAGE
- WRITE_EXTERNAL_STORAGE (Android < 33)
- POST_NOTIFICATIONS (Android 13+)
- VIBRATE
- WAKE_LOCK

**iOS (Info.plist):**
- NSCameraUsageDescription
- NSLocationWhenInUseUsageDescription
- NSLocationAlwaysAndWhenInUseUsageDescription
- NSPhotoLibraryUsageDescription
- NSPhotoLibraryAddUsageDescription
- NSMicrophoneUsageDescription (se webview pedir)

### R6: Botão voltar (Android)
- Se WebView pode voltar, botão back nativo navega no histórico do WebView
- Se não pode voltar, sai do app

### R7: Pull to refresh
- Puxar pra baixo na tela recarrega o WebView

### R8: Tela offline
- Detectar perda de conexão
- Mostrar tela "Sem conexão" com botão "Tentar novamente"

### R9: Links externos
- Links `mailto:`, `tel:`, `https://outro-dominio.com` abrem fora do app (no browser/email)
- Links da própria aplicação abrem dentro do WebView

### R10: Backend - identificação do app
- Middleware no Laravel detecta header `X-Client: 5estrelas-app`
- Compartilha em Inertia shared props: `is_mobile_app: true`
- Frontend pode usar isso pra esconder elementos só-web (ex: link de download do app, etc)

### R11: Branding inicial
- Logo placeholder em `mobile/assets/logo.png` (logo padrão "5E" ou genérico)
- Nome inicial do app: "5 Estrelas"
- Package name (Android): `com.bstechsolutions.cincoestrelas`
- Bundle ID (iOS): mesmo
- Stub de script `mobile/scripts/sync-branding.sh` com TODO

## Entregável
- `cd mobile && flutter run` abre o app no emulador/celular
- App mostra splash screen com logo
- Carrega `http://10.0.2.2:8000` (Laravel local)
- Tela de login aparece dentro do app
- Login funciona, dashboard aparece
- Botão voltar do Android navega no WebView
- Pull to refresh funciona
- Desconectar wifi → mostra tela offline com botão de retry
- Backend recebe header `X-Client: 5estrelas-app`

## Fora do escopo
- Push notifications (FCM) - futura spec
- Notificações in-app (Reverb) - futura spec
- Sincronização offline / cache de dados
- Login nativo (login fica no WebView)
- Build de produção (gerar APK assinado)
- Implementação completa do `sync-branding.sh` (apenas stub)
