import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:younz_digital_center/src/api_client.dart';
import 'package:younz_digital_center/src/app.dart';
import 'package:younz_digital_center/src/models.dart';
import 'package:younz_digital_center/src/screens/account_screen.dart';
import 'package:younz_digital_center/src/screens/ai_chat_screen.dart';
import 'package:younz_digital_center/src/screens/home_screen.dart';
import 'package:younz_digital_center/src/screens/product_detail_screen.dart';
import 'package:younz_digital_center/src/screens/products_screen.dart';
import 'package:younz_digital_center/src/screens/services_screen.dart';
import 'package:younz_digital_center/src/screens/topup_screen.dart';
import 'package:younz_digital_center/src/screens/track_screen.dart';
import 'package:younz_digital_center/src/theme.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  for (final size in const [Size(360, 800), Size(412, 915)]) {
    testWidgets('primary screens render without overflow at $size', (
      tester,
    ) async {
      final api = _FakeApiClient();
      final screens = <String, Widget>{
        'home': HomeScreen(api: api, onNavigate: (_) {}),
        'services': ServicesScreen(api: api),
        'products': ProductsScreen(api: api),
        'product detail': ProductDetailScreen(
          api: api,
          productId: 1,
        ),
        'top up': TopupScreen(api: api),
        'track': TrackScreen(api: api),
        'AI chat': AiChatScreen(api: api),
        'account': AccountScreen(api: api),
      };

      for (final entry in screens.entries) {
        await _pumpScreen(tester, entry.value, size: size);
        await _scrollThrough(tester);
        _expectNoFlutterException(tester, entry.key);
      }
    });
  }

  testWidgets('portal summary remains usable with 200 percent text', (
    tester,
  ) async {
    await _pumpScreen(
      tester,
      AccountScreen(api: _FakeApiClient(loggedIn: true)),
      size: const Size(360, 800),
      textScale: 2,
    );
    await _scrollThrough(tester);
    _expectNoFlutterException(tester, 'account portal at 200% text');
  });

  testWidgets('primary screens remain usable with 200 percent text', (
    tester,
  ) async {
    final api = _FakeApiClient();
    final screens = <String, Widget>{
      'home': HomeScreen(api: api, onNavigate: (_) {}),
      'services': ServicesScreen(api: api),
      'products': ProductsScreen(api: api),
      'product detail': ProductDetailScreen(
        api: api,
        productId: 1,
      ),
      'top up': TopupScreen(api: api),
      'track': TrackScreen(api: api),
      'AI chat': AiChatScreen(api: api),
      'account sign in': AccountScreen(api: api),
    };

    for (final entry in screens.entries) {
      await _pumpScreen(
        tester,
        entry.value,
        size: const Size(360, 800),
        textScale: 2,
      );
      await _scrollThrough(tester);
      _expectNoFlutterException(tester, '${entry.key} at 200% text');
    }
  });

  testWidgets('navigation dock switches screens with accessible targets', (
    tester,
  ) async {
    await _pumpScreen(
      tester,
      HomeShell(api: _FakeApiClient()),
      size: const Size(360, 800),
    );
    _expectNoFlutterException(tester, 'navigation dock');

    final productTarget = find
        .ancestor(of: find.text('Produk'), matching: find.byType(InkWell))
        .first;
    final productTargetSize = tester.getSize(productTarget);
    expect(productTargetSize.width, greaterThanOrEqualTo(44));
    expect(productTargetSize.height, greaterThanOrEqualTo(44));

    await tester.tap(find.text('Produk'));
    await tester.pump(const Duration(milliseconds: 300));
    expect(
      find.text('Pilih akses. Tentukan varian. Pesan langsung.'),
      findsOneWidget,
    );
    _expectNoFlutterException(tester, 'product navigation');

    final topUpTarget = find
        .ancestor(of: find.text('Top Up'), matching: find.byType(InkWell))
        .first;
    final targetSize = tester.getSize(topUpTarget);
    expect(targetSize.width, greaterThanOrEqualTo(44));
    expect(targetSize.height, greaterThanOrEqualTo(44));

    await tester.tap(find.text('Top Up'));
    await tester.pump(const Duration(milliseconds: 300));
    expect(find.text('Isi ulang. Bayar. Selesai.'), findsOneWidget);
    _expectNoFlutterException(tester, 'top up navigation');
  });

  testWidgets('app opens on login before exposing the main navigation', (
    tester,
  ) async {
    await _pumpScreen(
      tester,
      AuthGate(api: _LoginFakeApiClient()),
      size: const Size(360, 800),
    );

    expect(find.text('Masuk ke portal Anda.'), findsOneWidget);
    expect(find.byTooltip('Menu'), findsNothing);
    expect(find.byType(HomeShell), findsNothing);
    _expectNoFlutterException(tester, 'login gate');
  });

  testWidgets('successful login unlocks the main application shell', (
    tester,
  ) async {
    final api = _LoginFakeApiClient();
    await _pumpScreen(
      tester,
      AuthGate(api: api),
      size: const Size(360, 800),
    );

    final fields = find.byType(TextFormField);
    await tester.enterText(fields.at(0), 'customer@example.com');
    await tester.enterText(fields.at(1), 'password');
    await tester.tap(find.text('Masuk ke portal'));
    await tester.pumpAndSettle();

    expect(find.byType(HomeShell), findsOneWidget);
    expect(find.byTooltip('Menu'), findsOneWidget);
    _expectNoFlutterException(tester, 'login gate transition');

    await tester.tap(find.text('Akun'));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Keluar'));
    await tester.pumpAndSettle();
    expect(find.text('Masuk ke portal Anda.'), findsOneWidget);
    expect(find.byType(HomeShell), findsNothing);
    _expectNoFlutterException(tester, 'logout returns to login gate');
  });

  testWidgets('registration shows verification guidance and supports resend', (
    tester,
  ) async {
    final api = _RegistrationFakeApiClient();
    await _pumpScreen(
      tester,
      AuthGate(api: api),
      size: const Size(360, 800),
    );

    await tester.tap(find.text('Daftar'));
    await tester.pumpAndSettle();
    final fields = find.byType(TextFormField);
    await tester.enterText(fields.at(0), 'Pelanggan Mobile');
    await tester.enterText(fields.at(1), 'mobile@example.com');
    await tester.enterText(fields.at(2), '08219207242');
    await tester.enterText(fields.at(3), 'MobilePassword123');
    final registerButton = find.text('Daftar dan verifikasi email');
    await tester.drag(
      find.byType(CustomScrollView).first,
      const Offset(0, -600),
    );
    await tester.pumpAndSettle();
    await tester.tap(registerButton);
    await tester.pumpAndSettle();

    expect(find.text('Akun berhasil dibuat.'), findsOneWidget);
    expect(find.text('mobile@example.com'), findsOneWidget);
    expect(find.text('Saya sudah verifikasi, masuk'), findsOneWidget);
    expect(api.registerCalls, 1);

    final resendButton = find.text('Kirim ulang email verifikasi');
    await tester.ensureVisible(resendButton);
    await tester.tap(resendButton);
    await tester.pumpAndSettle();
    expect(api.resendCalls, 1);
    expect(
      find.text('Tautan verifikasi baru telah dikirim ke email Anda.'),
      findsOneWidget,
    );
    _expectNoFlutterException(tester, 'registration verification guidance');
  });

  testWidgets('navigation dock supports 200 percent text', (tester) async {
    await _pumpScreen(
      tester,
      HomeShell(api: _FakeApiClient()),
      size: const Size(360, 800),
      textScale: 2,
    );
    _expectNoFlutterException(tester, 'navigation dock at 200% text');
  });

  testWidgets('hamburger menu opens and drawer destinations switch tabs', (
    tester,
  ) async {
    await _pumpScreen(
      tester,
      HomeShell(api: _FakeApiClient()),
      size: const Size(360, 800),
    );

    await tester.tap(find.byTooltip('Menu'));
    await tester.pumpAndSettle();
    expect(find.text('NAVIGASI'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('drawer-Produk')));
    await tester.pumpAndSettle();
    expect(
      find.text('Pilih akses. Tentukan varian. Pesan langsung.'),
      findsOneWidget,
    );
    expect(find.text('NAVIGASI'), findsNothing);
    _expectNoFlutterException(tester, 'drawer product navigation');
  });

  testWidgets('assistant buttons open the native AI chat screen', (
    tester,
  ) async {
    await _pumpScreen(
      tester,
      HomeShell(api: _FakeApiClient()),
      size: const Size(360, 800),
    );

    await tester.tap(find.byTooltip('Buka Younz AI'));
    await tester.pumpAndSettle();
    expect(find.byType(AiChatScreen), findsOneWidget);
    _expectNoFlutterException(tester, 'top bar AI navigation');

    await tester.tap(find.byIcon(Icons.arrow_back_rounded));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Menu'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('drawer-younz-ai')));
    await tester.pumpAndSettle();
    expect(find.byType(AiChatScreen), findsOneWidget);
    _expectNoFlutterException(tester, 'drawer AI navigation');
  });

  testWidgets('account history and settings menus open useful content', (
    tester,
  ) async {
    await _pumpScreen(
      tester,
      HomeShell(api: _FakeApiClient(loggedIn: true)),
      size: const Size(360, 800),
    );

    await tester.tap(find.text('Akun'));
    await tester.pumpAndSettle();

    final viewAll = find.byKey(const ValueKey('account-view-all-orders'));
    await tester.scrollUntilVisible(viewAll, 320);
    await tester.tap(viewAll);
    await tester.pumpAndSettle();
    expect(find.text('Semua Riwayat'), findsOneWidget);

    await tester.tap(find.text('Tutup'));
    await tester.pumpAndSettle();

    final security = find.byKey(const ValueKey('account-setting-security'));
    await tester.scrollUntilVisible(security, 260);
    await tester.tap(security);
    await tester.pumpAndSettle();
    expect(
      find.descendant(
        of: find.byType(AlertDialog),
        matching: find.text('Security & Passwords'),
      ),
      findsOneWidget,
    );

    await tester.tap(find.text('Mengerti'));
    await tester.pumpAndSettle();

    final notifications =
        find.byKey(const ValueKey('account-setting-notifications'));
    await tester.scrollUntilVisible(notifications, 220);
    await tester.tap(notifications);
    await tester.pumpAndSettle();
    expect(find.text('Update pesanan'), findsOneWidget);
    await tester.tap(find.text('Promo & produk baru'));
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();

    final profile = find.byKey(const ValueKey('account-setting-profile'));
    await tester.scrollUntilVisible(profile, -180);
    await tester.tap(profile);
    await tester.pumpAndSettle();
    expect(
      find.descendant(
        of: find.byType(AlertDialog),
        matching: find.text('Profile Information'),
      ),
      findsOneWidget,
    );
    await tester.tap(find.text('Tutup'));
    await tester.pumpAndSettle();

    final help = find.byKey(const ValueKey('account-setting-help'));
    await tester.scrollUntilVisible(help, 260);
    await tester.tap(help);
    await tester.pumpAndSettle();
    expect(
      find.descendant(
        of: find.byType(AlertDialog),
        matching: find.text('Help Center'),
      ),
      findsOneWidget,
    );
    await tester.tap(find.text('Mengerti'));
    await tester.pumpAndSettle();
    _expectNoFlutterException(tester, 'account action menus');
  });

  testWidgets('product category filter updates the visible catalog', (
    tester,
  ) async {
    await _pumpScreen(
      tester,
      ProductsScreen(api: _FakeApiClient()),
      size: const Size(360, 800),
    );

    expect(find.text('ChatGPT Plus'), findsOneWidget);
    expect(find.text('YouTube Premium'), findsOneWidget);

    final streamingFilter =
        find.byKey(const ValueKey('product-category-Streaming'));
    await tester.ensureVisible(streamingFilter);
    await tester.pumpAndSettle();
    await tester.tap(streamingFilter);
    await tester.pump();

    expect(find.text('ChatGPT Plus'), findsNothing);
    expect(find.text('YouTube Premium'), findsOneWidget);
    _expectNoFlutterException(tester, 'product category filter');
  });

  testWidgets(
      'product detail requires a variant and opens in-app Midtrans checkout', (
    tester,
  ) async {
    final api = _FakeApiClient();
    DigitalProductOrderCheckout? checkout;
    await _pumpScreen(
      tester,
      ProductDetailScreen(
        api: api,
        productId: 1,
        paymentLauncher: (context, value) async {
          checkout = value;
        },
      ),
      size: const Size(360, 800),
    );

    final disabledButton = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Pilih varian dahulu'),
    );
    expect(disabledButton.onPressed, isNull);

    final monthlyVariant = find.byKey(const ValueKey('product-variant-101'));
    await tester.ensureVisible(monthlyVariant);
    await tester.pumpAndSettle();
    await tester.tap(monthlyVariant);
    await tester.pump();
    expect(tester.takeException(), isNull, reason: 'after variant selection');
    expect(
      find.byKey(const ValueKey('product-effective-price')),
      findsOneWidget,
    );
    expect(find.text('Rp 99.000'), findsWidgets);
    expect(find.text('4 tersedia'), findsWidgets);
    expect(find.text('Bayar Midtrans'), findsOneWidget);

    final increaseQuantity = find.byKey(const ValueKey('quantity-increase'));
    await tester.ensureVisible(increaseQuantity);
    await tester.pumpAndSettle();
    await tester.tap(increaseQuantity);
    await tester.pump();
    expect(tester.takeException(), isNull, reason: 'after quantity change');
    expect(find.text('2'), findsOneWidget);

    await tester.tap(find.text('Bayar Midtrans'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull,
        reason: 'while checkout sheet is open');
    expect(find.text('Buat pesanan & bayar'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('confirm-product-checkout')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull, reason: 'after checkout submission');
    expect(checkout?.orderNumber, 'ORD-ANDROID-0001');
    expect(checkout?.redirectUrl, contains('midtrans.test'));
    expect(api.lastDigitalProductQuantity, 2);
    expect(api.lastDigitalProductVariantId, 101);
    _expectNoFlutterException(tester, 'product Midtrans order');
  });

  testWidgets('out of stock variant disables product checkout', (
    tester,
  ) async {
    await _pumpScreen(
      tester,
      ProductDetailScreen(
        api: _FakeApiClient(),
        productId: 1,
      ),
      size: const Size(412, 915),
    );

    final quarterlyVariant = find.byKey(const ValueKey('product-variant-102'));
    await tester.ensureVisible(quarterlyVariant);
    await tester.pumpAndSettle();
    await tester.tap(quarterlyVariant);
    await tester.pump();
    expect(find.text('Habis'), findsWidgets);
    final button = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Stok habis'),
    );
    expect(button.onPressed, isNull);
    _expectNoFlutterException(tester, 'out of stock product checkout');
  });
}

Future<void> _pumpScreen(
  WidgetTester tester,
  Widget screen, {
  required Size size,
  double textScale = 1,
}) async {
  tester.view.devicePixelRatio = 1;
  tester.view.physicalSize = size;
  addTearDown(tester.view.resetDevicePixelRatio);
  addTearDown(tester.view.resetPhysicalSize);

  await tester.pumpWidget(
    MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: buildYounzTheme(),
      home: MediaQuery(
        data: MediaQueryData(
          size: size,
          devicePixelRatio: 1,
          textScaler: TextScaler.linear(textScale),
        ),
        child: Scaffold(body: screen),
      ),
    ),
  );
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 500));
}

Future<void> _scrollThrough(WidgetTester tester) async {
  for (var index = 0; index < 12; index++) {
    final scrollables = find.byType(Scrollable);
    if (scrollables.evaluate().isEmpty) return;
    await tester.drag(
      scrollables.first,
      const Offset(0, -520),
      warnIfMissed: false,
    );
    await tester.pump(const Duration(milliseconds: 180));
  }
}

void _expectNoFlutterException(WidgetTester tester, String screen) {
  final exceptions = <String>[];
  Object? exception;
  while ((exception = tester.takeException()) != null) {
    exceptions.add(exception.toString());
  }
  expect(exceptions, isEmpty, reason: '$screen emitted: $exceptions');
}

class _FakeApiClient extends ApiClient {
  _FakeApiClient({this.loggedIn = false})
      : super(baseUrl: 'https://example.test');

  final bool loggedIn;
  int? lastDigitalProductVariantId;
  int? lastDigitalProductQuantity;

  static const services = [
    ServiceItem(
      id: 1,
      name: 'Print dokumen A4',
      type: 'print',
      unit: 'lembar',
      basePrice: 500,
      description: 'Cetak hitam putih atau berwarna untuk dokumen harian.',
    ),
    ServiceItem(
      id: 2,
      name: 'Desain materi promosi',
      type: 'desain',
      unit: 'proyek',
      basePrice: 75000,
      description: 'Desain yang disiapkan sesuai kebutuhan kanal dan brand.',
    ),
    ServiceItem(
      id: 3,
      name: 'Website bisnis',
      type: 'website',
      unit: 'proyek',
      basePrice: 1500000,
      description:
          'Website responsif untuk profil, katalog, dan kebutuhan bisnis.',
    ),
  ];

  static const products = [
    DigitalProduct(
      id: 1,
      name: 'ChatGPT Plus',
      category: 'AI Assistant',
      mark: 'CG',
      imageUrl: '',
      price: null,
      priceLabel: 'Mulai Rp 99.000',
      stock: null,
      stockLabel: 'Sesuai varian',
      description: 'Akses akun premium dengan panduan aktivasi.',
      sortOrder: 1,
      variants: [
        DigitalVariant(
          id: 101,
          label: '1 Bulan',
          price: 99000,
          priceLabel: 'Rp 99.000',
          stock: 4,
          stockLabel: '4 tersedia',
          sortOrder: 1,
        ),
        DigitalVariant(
          id: 102,
          label: '3 Bulan',
          price: 249000,
          priceLabel: 'Rp 249.000',
          stock: 0,
          stockLabel: 'Habis',
          sortOrder: 2,
        ),
      ],
    ),
    DigitalProduct(
      id: 2,
      name: 'YouTube Premium',
      category: 'Streaming',
      mark: 'YT',
      imageUrl: '',
      price: 59000,
      priceLabel: 'Rp 59.000',
      stock: 7,
      stockLabel: '7 tersedia',
      description: 'Paket premium untuk menikmati konten tanpa iklan.',
      sortOrder: 2,
      variants: [],
    ),
  ];

  static const topups = [
    TopupProduct(
      id: 1,
      transactionType: 'prepaid',
      name: 'Telkomsel 25.000',
      category: 'Pulsa',
      brand: 'Telkomsel',
      description: 'Pulsa reguler untuk nomor Telkomsel.',
      price: 27000,
      formType: 'phone',
    ),
    TopupProduct(
      id: 2,
      transactionType: 'prepaid',
      name: 'Token PLN 50.000',
      category: 'Listrik',
      brand: 'PLN',
      description: 'Token listrik prabayar yang diproses otomatis.',
      price: 51500,
      formType: 'meter',
    ),
  ];

  @override
  Future<bool> get hasSession async => loggedIn;

  @override
  Future<SiteContent> fetchSite() async => const SiteContent(
        services: services,
        products: [],
        store: StoreInfo(
          address: 'Jl. Kapten Mulyono No. 60C, Sampit',
          openHours: 'Senin - Sabtu, 09.00 - 17.00',
          mapsUrl: '',
          whatsapp: '628219207240',
        ),
      );

  @override
  Future<List<ServiceItem>> fetchServices() async => services;

  @override
  Future<List<DigitalProduct>> fetchDigitalProducts() async => products;

  @override
  Future<DigitalProduct> fetchDigitalProduct(int id) async =>
      products.firstWhere((product) => product.id == id);

  @override
  String createIdempotencyKey() => 'android-test-idempotency-key';

  @override
  Future<DigitalProductOrderCheckout> createDigitalProductOrder({
    required int productId,
    required int quantity,
    required String idempotencyKey,
    int? variantId,
  }) async {
    lastDigitalProductVariantId = variantId;
    lastDigitalProductQuantity = quantity;
    return const DigitalProductOrderCheckout(
      id: 77,
      orderNumber: 'ORD-ANDROID-0001',
      message: 'Pesanan dibuat.',
      paymentRequired: true,
      paymentStatus: 'pending',
      amount: 198000,
      redirectUrl: 'https://midtrans.test/snap/checkout',
      trackingToken: 'tracking-test',
    );
  }

  @override
  Future<DigitalProductOrderStatus> fetchDigitalProductOrderStatus(
    int orderId,
  ) async =>
      const DigitalProductOrderStatus(
        id: 77,
        orderNumber: 'ORD-ANDROID-0001',
        productName: 'ChatGPT Plus',
        variantLabel: '1 Bulan',
        quantity: 2,
        paymentStatus: 'pending',
        paymentTerminal: false,
        amount: 198000,
        paidAmount: 0,
        redirectUrl: 'https://midtrans.test/snap/checkout',
        orderStatusCode: 'awaiting_payment',
        orderStatusLabel: 'Menunggu pembayaran',
      );

  @override
  Future<DigitalProductOrderStatus> refreshDigitalProductOrderPayment(
    int orderId,
  ) async =>
      fetchDigitalProductOrderStatus(orderId);

  @override
  Future<TopupCatalog> fetchTopupCatalog({String mode = 'prepaid'}) async =>
      TopupCatalog(
        products: topups,
        categories: const ['Pulsa', 'Listrik'],
        brands: const ['Telkomsel', 'PLN'],
        modeLabel: mode == 'prepaid' ? 'Prabayar' : 'Pascabayar',
        integrationsReady: true,
      );

  @override
  Future<PortalData> fetchPortal() async => const PortalData(
        name: 'Daffa Younz',
        email: 'daffa@example.com',
        phone: '0821 9207 2400',
        totalOrders: 12,
        activeOrders: 3,
        completedOrders: 9,
        orders: [
          OrderSummary(
            id: 1,
            orderNumber: 'YDC-20260814-001',
            type: 'website',
            service: 'Website bisnis',
            statusCode: 'in_progress',
            statusLabel: 'Sedang dikerjakan',
            estimatedPrice: 1500000,
            finalPrice: 1750000,
            paidAmount: 750000,
            deadlineAt: '2026-08-20T10:00:00+07:00',
            createdAt: '2026-08-14T10:00:00+07:00',
          ),
        ],
      );
}

class _LoginFakeApiClient extends _FakeApiClient {
  _LoginFakeApiClient() : super();

  bool active = false;

  @override
  Future<bool> get hasSession async => active;

  @override
  Future<void> login({required String email, required String password}) async {
    active = true;
  }

  @override
  Future<void> logout() async {
    active = false;
  }
}

class _RegistrationFakeApiClient extends _FakeApiClient {
  _RegistrationFakeApiClient() : super();

  int registerCalls = 0;
  int resendCalls = 0;

  @override
  Future<String> register({
    required String name,
    required String email,
    required String phone,
    required String password,
  }) async {
    registerCalls++;
    return 'Akun berhasil dibuat. Buka tautan verifikasi yang dikirim ke email Anda.';
  }

  @override
  Future<String> resendEmailVerification({
    required String email,
    required String password,
  }) async {
    resendCalls++;
    return 'Tautan verifikasi baru telah dikirim ke email Anda.';
  }
}
