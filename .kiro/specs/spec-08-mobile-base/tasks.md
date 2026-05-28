# Spec 08 - Tasks

## Setup
- [ ] 1. Criar projeto Flutter em `mobile/`
- [ ] 2. Adicionar dependências no `pubspec.yaml`
- [ ] 3. Configurar `flutter_native_splash.yaml`
- [ ] 4. Configurar `flutter_launcher_icons.yaml`
- [ ] 5. Adicionar logo placeholder em `assets/`

## Configuração
- [ ] 6. `lib/config/app_config.dart` com URL e cores
- [ ] 7. Permissões no `AndroidManifest.xml` (incluindo `usesCleartextTraffic`)
- [ ] 8. Permissões no `Info.plist` do iOS

## Telas
- [ ] 9. `lib/main.dart` com bootstrap e status bar
- [ ] 10. `lib/screens/webview_screen.dart` (WebView + headers + back button + pull to refresh + redirect externo)
- [ ] 11. `lib/screens/offline_screen.dart` (tela "sem conexão")

## Stub e docs
- [ ] 12. `mobile/scripts/sync-branding.sh` (stub com TODO)
- [ ] 13. `mobile/README.md` com instruções de uso

## Backend
- [ ] 14. `HandleInertiaRequests` compartilha `is_mobile_app`

## DemoSeeder
- [ ] N/A (mobile-base não cria entidades)

## Validação
- [ ] 15. `flutter pub get` ok
- [ ] 16. App roda no emulador apontando para Laravel local
- [ ] 17. Login e dashboard funcionam dentro do WebView
- [ ] 18. Botão back navega no histórico
- [ ] 19. Pull to refresh funciona
- [ ] 20. Sem internet → tela offline aparece
- [ ] 21. Backend recebe header `X-Client: 5estrelas-app`

## Status: 🔵 Em andamento
