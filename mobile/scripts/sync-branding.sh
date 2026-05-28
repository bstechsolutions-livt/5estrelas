#!/bin/bash
# Script de sincronização de branding do app mobile com as settings do sistema.
#
# TODO: implementar conforme regra em .kiro/steering/mobile-build.md
#
# Passos esperados:
# 1. Ler settings do banco do Laravel (app_name, primary_color, secondary_color, logo_path, favicon_path)
# 2. Copiar logo do storage para mobile/assets/splash_logo.png
# 3. Copiar favicon (ou logo) para mobile/assets/launcher_icon.png
# 4. Atualizar pubspec.yaml (campo name e versionCode)
# 5. Atualizar lib/config/app_config.dart com cores
# 6. Atualizar AndroidManifest.xml (android:label) e Info.plist (CFBundleDisplayName)
# 7. Rodar flutter pub run flutter_native_splash:create
# 8. Rodar flutter pub run flutter_launcher_icons:main
#
# Uso:
#   cd mobile && ./scripts/sync-branding.sh

set -e

echo "⚠️  TODO: sync-branding.sh ainda não implementado."
echo "    Veja .kiro/steering/mobile-build.md"
exit 0
