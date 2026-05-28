import 'dart:async';
import 'dart:io';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';

import '../config/app_config.dart';
import '../services/push_service.dart';
import 'offline_screen.dart';

class WebViewScreen extends StatefulWidget {
  const WebViewScreen({super.key});

  @override
  State<WebViewScreen> createState() => _WebViewScreenState();
}

class _WebViewScreenState extends State<WebViewScreen> {
  late final WebViewController _controller;
  bool _isLoading = true;
  bool _isOffline = false;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;

  @override
  void initState() {
    super.initState();
    _initWebView();
    _initConnectivityListener();
  }

  @override
  void dispose() {
    _connectivitySubscription?.cancel();
    super.dispose();
  }

  void _initWebView() {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(AppConfig.secondaryColor)
      ..setUserAgent(_buildUserAgent())
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) {
            if (mounted) setState(() => _isLoading = true);
          },
          onPageFinished: (url) async {
            if (mounted) setState(() => _isLoading = false);
            // Quando navega pra qualquer página autenticada, tenta registrar o token push.
            // (silencioso se Firebase não estiver inicializado ou se ainda não logou)
            if (PushService().isInitialized && !url.contains('/login')) {
              _registerPushToken();
            }
          },
          onWebResourceError: (error) {
            _checkConnection();
          },
          onNavigationRequest: (request) {
            // URLs de download → baixa via Dio (pegando cookies do WebView)
            if (_isDownloadUrl(request.url)) {
              _downloadFile(request.url);
              return NavigationDecision.prevent;
            }
            if (_isExternalUrl(request.url)) {
              _openExternal(request.url);
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(
        Uri.parse(AppConfig.backendUrl),
        headers: AppConfig.defaultHeaders,
      );

    // Habilita seletor de arquivo no Android
    if (_controller.platform is AndroidWebViewController) {
      (_controller.platform as AndroidWebViewController)
          .setOnShowFileSelector(_androidFilePicker);
    }
  }

  Future<List<String>> _androidFilePicker(FileSelectorParams params) async {
    final acceptTypes = params.acceptTypes;
    final isImage = acceptTypes.any((t) => t.contains('image'));

    // Se for só imagem, usa image_picker (com câmera ou galeria)
    if (isImage && params.mode != FileSelectorMode.openMultiple) {
      return _showImageSourceDialog();
    }

    // Caso contrário, usa file_picker
    final result = await FilePicker.platform.pickFiles(
      type: isImage ? FileType.image : FileType.any,
      allowMultiple: params.mode == FileSelectorMode.openMultiple,
    );

    if (result == null) return [];

    return result.files
        .where((f) => f.path != null)
        .map((f) => Uri.file(f.path!).toString())
        .toList();
  }

  Future<List<String>> _showImageSourceDialog() async {
    if (!mounted) return [];

    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Selecionar foto',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
              ),
            ),
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined),
              title: const Text('Tirar foto'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Escolher da galeria'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
            ListTile(
              leading: const Icon(Icons.folder_outlined),
              title: const Text('Buscar nos arquivos'),
              onTap: () => Navigator.pop(ctx, null),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );

    if (source != null) {
      final picker = ImagePicker();
      final XFile? file = await picker.pickImage(source: source, imageQuality: 85);
      if (file == null) return [];
      return [Uri.file(file.path).toString()];
    }

    // Fallback para arquivos quaisquer
    final result = await FilePicker.platform.pickFiles(type: FileType.image);
    if (result == null || result.files.isEmpty || result.files.first.path == null) {
      return [];
    }
    return [Uri.file(result.files.first.path!).toString()];
  }

  String _buildUserAgent() {
    return 'Mozilla/5.0 (Linux; Android) AppleWebKit/537.36 ${AppConfig.userAgentSuffix}';
  }

  bool _isExternalUrl(String url) {
    final uri = Uri.tryParse(url);
    if (uri == null) return false;

    // Schemes especiais sempre externos
    if (['mailto', 'tel', 'sms', 'whatsapp'].contains(uri.scheme)) {
      return true;
    }

    if (uri.scheme != 'http' && uri.scheme != 'https') {
      return true;
    }

    final host = uri.host.isEmpty ? '' : '${uri.host}${uri.hasPort ? ':${uri.port}' : ''}';
    final internalHosts = AppConfig.internalHosts;

    return !internalHosts.any((h) =>
        host == h || host == h.split(':').first);
  }

  /// Detecta URLs que sabemos serem de download (zip, pdf, xlsx, csv, dump, etc).
  /// O WebView do Android não baixa nativamente, então enviamos pro browser.
  bool _isDownloadUrl(String url) {
    final uri = Uri.tryParse(url);
    if (uri == null) return false;

    final lower = uri.path.toLowerCase();
    const downloadExt = ['.zip', '.pdf', '.xlsx', '.xls', '.csv', '.doc', '.docx', '.dump', '.sql', '.tar', '.gz', '.7z'];
    if (downloadExt.any((ext) => lower.endsWith(ext))) return true;

    // Padrões de rota que sabemos ser download
    if (lower.contains('/download') || lower.contains('/backups/') && lower.endsWith('/download')) {
      return true;
    }

    return false;
  }

  Future<void> _openExternal(String url) async {
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  /// Baixa o arquivo via Dio usando os cookies da sessão atual do WebView,
  /// salva em /storage/emulated/0/Download (ou diretório padrão de Downloads)
  /// e abre com o app padrão.
  Future<void> _downloadFile(String url) async {
    if (!mounted) return;
    final scaffold = ScaffoldMessenger.of(context);

    // Nome final do arquivo (último segmento ou random)
    String filename = url.split('/').lastWhere(
          (p) => p.isNotEmpty,
          orElse: () => 'arquivo',
        );
    if (!filename.contains('.')) {
      // Para URLs como /backups/<file>/download, pega o segmento anterior
      final segments = Uri.parse(url).pathSegments;
      if (segments.length >= 2) {
        for (final s in segments.reversed) {
          if (s.contains('.')) {
            filename = s;
            break;
          }
        }
      }
    }

    scaffold.showSnackBar(
      SnackBar(
        content: Text('Baixando $filename...'),
        duration: const Duration(seconds: 30),
      ),
    );

    try {
      // Pega cookies da sessão do WebView
      final cookieJs = await _controller.runJavaScriptReturningResult('document.cookie');
      var cookieStr = cookieJs.toString();
      if (cookieStr.startsWith('"') && cookieStr.endsWith('"')) {
        cookieStr = cookieStr.substring(1, cookieStr.length - 1);
      }

      final dio = Dio();
      dio.options.headers = {
        ...AppConfig.defaultHeaders,
        'Cookie': cookieStr,
        'User-Agent': _buildUserAgent(),
      };

      // Diretório de destino: Downloads público no Android
      Directory? targetDir;
      if (Platform.isAndroid) {
        targetDir = Directory('/storage/emulated/0/Download');
        if (!await targetDir.exists()) {
          targetDir = await getExternalStorageDirectory();
        }
      } else {
        targetDir = await getApplicationDocumentsDirectory();
      }
      targetDir ??= await getApplicationDocumentsDirectory();

      final savePath = '${targetDir.path}/$filename';
      await dio.download(url, savePath);

      scaffold.hideCurrentSnackBar();
      scaffold.showSnackBar(
        SnackBar(
          content: Text('Salvo em Downloads: $filename'),
          action: SnackBarAction(
            label: 'Abrir',
            onPressed: () => OpenFilex.open(savePath),
          ),
          duration: const Duration(seconds: 6),
        ),
      );
    } catch (e) {
      scaffold.hideCurrentSnackBar();
      scaffold.showSnackBar(
        SnackBar(content: Text('Falha ao baixar: ${e.toString()}')),
      );
    }
  }

  void _initConnectivityListener() {
    _connectivitySubscription = Connectivity().onConnectivityChanged.listen((results) {
      final hasNetwork = results.any((r) => r != ConnectivityResult.none);
      if (mounted) {
        setState(() => _isOffline = !hasNetwork);
      }
    });

    // Verifica estado inicial
    _checkConnection();
  }

  Future<void> _checkConnection() async {
    final results = await Connectivity().checkConnectivity();
    final hasNetwork = results.any((r) => r != ConnectivityResult.none);
    if (mounted) {
      setState(() => _isOffline = !hasNetwork);
    }
  }

  Future<void> _refresh() async {
    await _controller.reload();
  }

  /// Pega cookies da sessão atual do WebView e registra o token FCM no backend.
  /// Roda em fire-and-forget; falhas são silenciosas (ex: ainda não logou).
  Future<void> _registerPushToken() async {
    try {
      final cookieJs = await _controller.runJavaScriptReturningResult('document.cookie');
      var cookieStr = cookieJs.toString();
      if (cookieStr.startsWith('"') && cookieStr.endsWith('"')) {
        cookieStr = cookieStr.substring(1, cookieStr.length - 1);
      }
      if (cookieStr.isEmpty || !cookieStr.contains('session')) {
        return; // não logado
      }
      await PushService().obtainAndRegisterToken(sessionCookie: cookieStr);
    } catch (_) {
      // ignora
    }
  }

  Future<void> _retry() async {
    await _checkConnection();
    if (!_isOffline) {
      _controller.loadRequest(
        Uri.parse(AppConfig.backendUrl),
        headers: AppConfig.defaultHeaders,
      );
    }
  }

  Future<bool> _onBackPressed() async {
    if (await _controller.canGoBack()) {
      await _controller.goBack();
      return false; // não fecha o app
    }
    return true; // fecha o app
  }

  @override
  Widget build(BuildContext context) {
    if (_isOffline) {
      return OfflineScreen(onRetry: _retry);
    }

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        final shouldPop = await _onBackPressed();
        if (shouldPop && mounted) {
          SystemNavigator.pop();
        }
      },
      child: Scaffold(
        backgroundColor: AppConfig.secondaryColor,
        body: SafeArea(
          child: Stack(
            children: [
              WebViewWidget(controller: _controller),
              if (_isLoading)
                Container(
                  color: AppConfig.secondaryColor,
                  child: Center(
                    child: CircularProgressIndicator(
                      color: AppConfig.primaryColor,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
