import 'dart:io';

import 'package:dio/dio.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../config/app_config.dart';

/// Gerencia inicialização do Firebase Messaging e registro do token no backend.
///
/// Roda de forma "silenciosa": se Firebase não estiver configurado
/// (sem google-services.json), o app continua funcionando normalmente.
class PushService {
  static final PushService _instance = PushService._();
  PushService._();
  factory PushService() => _instance;

  FirebaseMessaging? _messaging;
  String? _currentToken;
  bool _initialized = false;
  final FlutterLocalNotificationsPlugin _local = FlutterLocalNotificationsPlugin();

  /// Inicializa Firebase + FCM. Falha silenciosa se não configurado.
  Future<void> init() async {
    if (_initialized) return;

    try {
      await Firebase.initializeApp();
      _messaging = FirebaseMessaging.instance;

      // Solicita permissão (iOS é obrigatório, Android 13+ também)
      await _messaging!.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );

      // Configura local notifications (mostrar push em foreground)
      await _initLocalNotifications();

      // Listeners
      FirebaseMessaging.onMessage.listen(_onMessageForeground);
      _messaging!.onTokenRefresh.listen((newToken) {
        _currentToken = newToken;
        registerTokenWithBackend(newToken);
      });

      _initialized = true;
      debugPrint('[PushService] Inicializado com sucesso.');
    } catch (e) {
      debugPrint('[PushService] Firebase não configurado ou falhou ao iniciar: $e');
      _initialized = false;
    }
  }

  Future<void> _initLocalNotifications() async {
    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosInit = DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
    );

    await _local.initialize(
      const InitializationSettings(android: androidInit, iOS: iosInit),
    );
  }

  void _onMessageForeground(RemoteMessage message) {
    final notification = message.notification;
    if (notification == null) return;

    // Mostra notificação local quando app está em foreground
    _local.show(
      notification.hashCode,
      notification.title,
      notification.body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          '5estrelas_default',
          '5 Estrelas',
          channelDescription: 'Notificações da plataforma 5 Estrelas',
          importance: Importance.high,
          priority: Priority.high,
        ),
        iOS: DarwinNotificationDetails(),
      ),
      payload: message.data['link']?.toString() ?? '',
    );
  }

  /// Pega token atual e registra no backend.
  /// Deve ser chamado depois do login (precisa do cookie de sessão).
  Future<void> obtainAndRegisterToken({String? sessionCookie}) async {
    if (!_initialized || _messaging == null) return;

    try {
      final token = await _messaging!.getToken();
      if (token == null) return;

      _currentToken = token;
      await registerTokenWithBackend(token, sessionCookie: sessionCookie);
    } catch (e) {
      debugPrint('[PushService] Falha ao obter token: $e');
    }
  }

  /// POST /device-tokens com token FCM. Requer cookie de sessão.
  Future<void> registerTokenWithBackend(String token, {String? sessionCookie}) async {
    try {
      final info = await PackageInfo.fromPlatform();
      final dio = Dio();

      final headers = <String, String>{
        ...AppConfig.defaultHeaders,
        'Accept': 'application/json',
      };

      if (sessionCookie != null && sessionCookie.isNotEmpty) {
        headers['Cookie'] = sessionCookie;
      }

      await dio.post(
        '${AppConfig.backendUrl}/device-tokens',
        data: {
          'token': token,
          'platform': Platform.isIOS ? 'ios' : 'android',
          'device_name': Platform.isIOS ? 'iOS Device' : 'Android Device',
          'app_version': info.version,
        },
        options: Options(headers: headers),
      );

      debugPrint('[PushService] Token registrado no backend.');
    } catch (e) {
      debugPrint('[PushService] Falha ao registrar token: $e');
    }
  }

  String? get currentToken => _currentToken;
  bool get isInitialized => _initialized;
}
