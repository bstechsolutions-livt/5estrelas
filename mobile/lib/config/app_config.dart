import 'package:flutter/material.dart';

class AppConfig {
  // URL do backend (configurável via --dart-define=BACKEND_URL=...)
  // Padrão: 10.0.2.2:8000 (emulador Android acessando localhost do host)
  static const String backendUrl = String.fromEnvironment(
    'BACKEND_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );

  // Header identificador para o backend reconhecer requisições do app
  static const Map<String, String> defaultHeaders = {
    'X-Client': '5estrelas-app',
  };

  // User agent customizado
  static const String userAgentSuffix = '5Estrelas/1.0.0';

  // Branding inicial — substituído pelo script sync-branding.sh em builds futuros
  static const String appName = 'Grupo 5 Estrelas';
  static const Color primaryColor = Color(0xFF6E93CF);
  static const Color secondaryColor = Color(0xFF04041A);

  // Hosts considerados internos (abrem dentro do WebView)
  static List<String> get internalHosts {
    final uri = Uri.parse(backendUrl);
    return [
      uri.host,
      '${uri.host}:${uri.port}',
    ];
  }
}
