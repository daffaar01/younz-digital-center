import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

import 'models.dart';

const defaultApiBaseUrl = String.fromEnvironment(
  'YOUNZ_API_URL',
  defaultValue: 'http://10.0.2.2:8000',
);

class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode, this.errors = const {}});

  final String message;
  final int? statusCode;
  final Map<String, List<String>> errors;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({String baseUrl = defaultApiBaseUrl})
      : baseUrl = baseUrl.replaceFirst(RegExp(r'/$'), '');

  final String baseUrl;
  final http.Client _http = http.Client();
  static const FlutterSecureStorage _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  Future<String?> get token => _storage.read(key: 'ydc_api_token');

  Future<bool> get hasSession async => (await token)?.isNotEmpty == true;

  Uri uri(String path, [Map<String, dynamic>? query]) {
    final raw = Uri.parse('$baseUrl$path');
    if (query == null) return raw;
    return raw.replace(
      queryParameters: query.map(
        (key, value) => MapEntry(key, value?.toString() ?? ''),
      ),
    );
  }

  String resolveUrl(String value) {
    if (value.isEmpty) return value;
    final parsed = Uri.tryParse(value);
    if (parsed?.hasScheme == true) return value;
    return '$baseUrl${value.startsWith('/') ? value : '/$value'}';
  }

  Future<Map<String, String>> _headers({
    bool authenticated = false,
    bool json = false,
  }) async {
    final result = <String, String>{'Accept': 'application/json'};
    if (json) result['Content-Type'] = 'application/json';
    if (authenticated) {
      final value = await token;
      if (value != null && value.isNotEmpty) {
        result['Authorization'] = 'Bearer $value';
      }
    }
    return result;
  }

  Future<JsonMap> _send(
    String method,
    String path, {
    JsonMap? body,
    Map<String, dynamic>? query,
    bool authenticated = false,
  }) async {
    try {
      final target = uri(path, query);
      final headers = await _headers(
        authenticated: authenticated,
        json: body != null,
      );
      late http.Response response;
      switch (method) {
        case 'POST':
          response = await _http
              .post(
                target,
                headers: headers,
                body: jsonEncode(body ?? const {}),
              )
              .timeout(const Duration(seconds: 35));
          break;
        case 'PATCH':
          response = await _http
              .patch(
                target,
                headers: headers,
                body: jsonEncode(body ?? const {}),
              )
              .timeout(const Duration(seconds: 35));
          break;
        case 'DELETE':
          response = await _http
              .delete(target, headers: headers)
              .timeout(const Duration(seconds: 25));
          break;
        default:
          response = await _http
              .get(target, headers: headers)
              .timeout(const Duration(seconds: 25));
          break;
      }
      return _decode(response);
    } on SocketException {
      throw const ApiException(
        'Backend tidak dapat dijangkau. Periksa URL API dan koneksi internet.',
      );
    } on HttpException {
      throw const ApiException(
        'Koneksi ke server terputus. Silakan coba lagi.',
      );
    } on TimeoutException {
      throw const ApiException(
        'Server terlalu lama merespons. Silakan coba lagi.',
      );
    } on FormatException {
      throw const ApiException(
        'Server mengirim respons yang tidak dapat dibaca.',
      );
    }
  }

  JsonMap _decode(http.Response response) {
    JsonMap payload = {};
    if (response.body.trim().isNotEmpty) {
      final decoded = jsonDecode(response.body);
      if (decoded is Map) payload = Map<String, dynamic>.from(decoded);
    }
    if (response.statusCode >= 200 && response.statusCode < 300) return payload;

    final validationErrors = <String, List<String>>{};
    final rawErrors = payload['errors'];
    if (rawErrors is Map) {
      for (final entry in rawErrors.entries) {
        final value = entry.value;
        validationErrors[entry.key.toString()] = value is List
            ? value.map((item) => item.toString()).toList()
            : <String>[value.toString()];
      }
    }
    throw ApiException(
      payload['message']?.toString() ??
          'Permintaan gagal (${response.statusCode}). Silakan coba lagi.',
      statusCode: response.statusCode,
      errors: validationErrors,
    );
  }

  Future<SiteContent> fetchSite() async {
    final payload = await _send('GET', '/api/v1/site');
    return SiteContent.fromJson(
      Map<String, dynamic>.from(payload['data'] as Map),
    );
  }

  Future<List<ServiceItem>> fetchServices() async {
    final payload = await _send('GET', '/api/v1/services');
    final values = payload['data'] as List? ?? const [];
    return values
        .whereType<Map>()
        .map((item) => ServiceItem.fromJson(Map<String, dynamic>.from(item)))
        .toList(growable: false);
  }

  Future<List<DigitalProduct>> fetchDigitalProducts() async {
    final payload = await _send('GET', '/api/v1/digital-products');
    final values = payload['data'] as List? ?? const [];
    return values
        .whereType<Map>()
        .map((item) => DigitalProduct.fromJson(Map<String, dynamic>.from(item)))
        .toList(growable: false);
  }

  Future<DigitalProduct> fetchDigitalProduct(int id) async {
    final payload = await _send('GET', '/api/v1/digital-products/$id');
    final data = payload['data'];
    if (data is! Map) {
      throw const ApiException('Detail produk tidak tersedia.');
    }
    return DigitalProduct.fromJson(Map<String, dynamic>.from(data));
  }

  String createIdempotencyKey() => _uuid();

  Future<DigitalProductOrderCheckout> createDigitalProductOrder({
    required int productId,
    required int quantity,
    required String idempotencyKey,
    int? variantId,
  }) async {
    final payload = await _send(
      'POST',
      '/api/v1/customer/digital-product-orders',
      authenticated: true,
      body: {
        'type': 'digital',
        'source': 'android-app',
        'idempotency_key': idempotencyKey,
        'product_id': productId,
        'quantity': quantity,
        if (variantId != null) 'variant_id': variantId,
      },
    );
    return DigitalProductOrderCheckout.fromJson(payload);
  }

  Future<DigitalProductOrderStatus> fetchDigitalProductOrderStatus(
    int orderId,
  ) async {
    final payload = await _send(
      'GET',
      '/api/v1/customer/digital-product-orders/$orderId/status',
      authenticated: true,
    );
    return DigitalProductOrderStatus.fromJson(
      Map<String, dynamic>.from(payload['data'] as Map),
    );
  }

  Future<DigitalProductOrderStatus> refreshDigitalProductOrderPayment(
    int orderId,
  ) async {
    final payload = await _send(
      'POST',
      '/api/v1/customer/digital-product-orders/$orderId/refresh-payment',
      authenticated: true,
    );
    return DigitalProductOrderStatus.fromJson(
      Map<String, dynamic>.from(payload['data'] as Map),
    );
  }

  Future<TopupCatalog> fetchTopupCatalog({String mode = 'prepaid'}) async {
    final payload = await _send(
      'GET',
      '/api/v1/topup/catalog',
      query: {'mode': mode},
    );
    return TopupCatalog.fromJson(
      Map<String, dynamic>.from(payload['data'] as Map),
    );
  }

  Future<ApiResult> createServiceOrder({
    required String customerName,
    required String customerPhone,
    required String type,
    int? serviceId,
    String? paperSize,
    String? colorMode,
    String? notes,
    File? file,
  }) async {
    final request = http.MultipartRequest('POST', uri('/api/v1/public/orders'));
    request.headers.addAll(await _headers(authenticated: await hasSession));
    request.fields.addAll({
      'source': 'direct',
      'customer_name': customerName.trim(),
      'customer_phone': customerPhone.trim(),
      'type': type,
      if (serviceId != null) 'service_id': serviceId.toString(),
      if (paperSize?.isNotEmpty == true)
        'specifications[paper_size]': paperSize!,
      if (colorMode?.isNotEmpty == true)
        'specifications[color_mode]': colorMode!,
      if (notes?.trim().isNotEmpty == true) 'notes': notes!.trim(),
    });
    if (file != null) {
      request.files.add(await http.MultipartFile.fromPath('file', file.path));
    }

    try {
      final streamed =
          await _http.send(request).timeout(const Duration(seconds: 60));
      final response = await http.Response.fromStream(streamed);
      final payload = _decode(response);
      final payment = payload['payment'] is Map
          ? Map<String, dynamic>.from(payload['payment'] as Map)
          : <String, dynamic>{};
      return ApiResult(
        message: payload['message']?.toString() ?? 'Pesanan berhasil dikirim.',
        data: payload['data'] is Map
            ? Map<String, dynamic>.from(payload['data'] as Map)
            : <String, dynamic>{},
        trackingToken: payload['tracking_token']?.toString(),
        redirectUrl: payment['redirect_url']?.toString(),
      );
    } on SocketException {
      throw const ApiException('Koneksi terputus saat mengunggah pesanan.');
    } on TimeoutException {
      throw const ApiException(
        'Unggah pesanan melebihi batas waktu. Silakan coba lagi.',
      );
    }
  }

  Future<ApiResult> trackOrder({
    required String orderNumber,
    required String phone,
  }) async {
    final payload = await _send(
      'POST',
      '/api/v1/public/orders/track',
      body: {'order_number': orderNumber.trim(), 'phone': phone.trim()},
    );
    return ApiResult(
      message: 'Pesanan ditemukan.',
      data: Map<String, dynamic>.from(payload['data'] as Map),
    );
  }

  Future<ApiResult> createTopup({
    required TopupProduct product,
    required String destination,
    required String customerName,
    required String customerEmail,
    required String customerPhone,
  }) async {
    final payload = await _send(
      'POST',
      '/api/v1/topup/checkout',
      body: {
        'idempotency_key': _uuid(),
        'product_id': product.id,
        'destination': destination.trim(),
        'destination_confirmation': destination.trim(),
        'customer_name': customerName.trim(),
        'customer_email': customerEmail.trim(),
        'customer_phone': customerPhone.trim(),
        'terms': true,
      },
    );
    return ApiResult(
      message: payload['message']?.toString() ?? 'Transaksi berhasil dibuat.',
      data: payload['data'] is Map
          ? Map<String, dynamic>.from(payload['data'] as Map)
          : <String, dynamic>{},
      redirectUrl: payload['redirect_url']?.toString(),
    );
  }

  Future<ChatReply> chat({
    required String message,
    required List<JsonMap> history,
  }) async {
    final payload = await _send(
      'POST',
      '/api/v1/ai/chat',
      body: {'message': message.trim(), 'history': history, 'ai_consent': '1'},
    );
    final data = Map<String, dynamic>.from(payload['data'] as Map);
    final sources = (data['sources'] as List? ?? const [])
        .map(
          (item) => item is Map
              ? (item['title'] ?? item['type'] ?? 'Sumber Younz').toString()
              : item.toString(),
        )
        .toList(growable: false);
    return ChatReply(
      message: data['answer']?.toString() ?? 'Belum ada jawaban.',
      sources: sources,
    );
  }

  Future<void> login({required String email, required String password}) async {
    final payload = await _send(
      'POST',
      '/api/v1/auth/customer-login',
      body: {
        'email': email.trim(),
        'password': password,
        'device_name': 'Younz Android',
      },
    );
    final data = Map<String, dynamic>.from(payload['data'] as Map);
    final value = data['token']?.toString();
    if (value == null || value.isEmpty) {
      throw const ApiException('Token login tidak tersedia.');
    }
    await _storage.write(key: 'ydc_api_token', value: value);
  }

  Future<String> register({
    required String name,
    required String email,
    required String phone,
    required String password,
  }) async {
    final payload = await _send(
      'POST',
      '/api/v1/auth/register',
      body: {
        'name': name.trim(),
        'email': email.trim(),
        'phone': phone.trim(),
        'password': password,
        'password_confirmation': password,
        'terms': true,
        'device_name': 'Younz Android',
      },
    );
    return payload['message']?.toString() ??
        'Akun dibuat. Silakan verifikasi email sebelum masuk.';
  }

  Future<String> resendEmailVerification({
    required String email,
    required String password,
  }) async {
    final payload = await _send(
      'POST',
      '/api/v1/auth/email-verification/resend',
      body: {
        'email': email.trim(),
        'password': password,
      },
    );
    return payload['message']?.toString() ??
        'Tautan verifikasi baru telah dikirim.';
  }

  Future<PortalData> fetchPortal() async {
    final payload = await _send(
      'GET',
      '/api/v1/customer/portal',
      authenticated: true,
    );
    return PortalData.fromJson(
      Map<String, dynamic>.from(payload['data'] as Map),
    );
  }

  Future<void> logout() async {
    try {
      await _send('POST', '/api/v1/auth/logout', authenticated: true);
    } catch (_) {
      // Local sign-out must still work when the backend is temporarily offline.
    } finally {
      await _storage.delete(key: 'ydc_api_token');
    }
  }

  String _uuid() {
    final random = Random.secure();
    String hex(int count) => List.generate(
          count,
          (_) => random.nextInt(16).toRadixString(16),
        ).join();
    return '${hex(8)}-${hex(4)}-4${hex(3)}-${(8 + random.nextInt(4)).toRadixString(16)}${hex(3)}-${hex(12)}';
  }
}
