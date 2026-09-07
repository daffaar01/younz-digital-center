typedef JsonMap = Map<String, dynamic>;

int _asInt(dynamic value, {int fallback = 0}) {
  if (value is int) return value;
  if (value is num) return value.round();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

int? _asIntOrNull(dynamic value) {
  if (value == null) return null;
  if (value is int) return value;
  if (value is num) return value.round();
  return int.tryParse(value.toString());
}

double? _asDoubleOrNull(dynamic value) {
  if (value == null) return null;
  if (value is num) return value.toDouble();
  return double.tryParse(value.toString());
}

String _asString(dynamic value, {String fallback = ''}) =>
    value?.toString() ?? fallback;

JsonMap _asMap(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<JsonMap> _asMapList(dynamic value) => value is List
    ? value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList()
    : <JsonMap>[];

class StoreInfo {
  const StoreInfo({
    required this.address,
    required this.openHours,
    required this.mapsUrl,
    required this.whatsapp,
  });

  factory StoreInfo.fromJson(JsonMap json) => StoreInfo(
        address: _asString(json['address'], fallback: 'Younz Digital Center'),
        openHours: _asString(
          json['open_hours'],
          fallback: 'Senin - Sabtu, 09.00 - 17.00',
        ),
        mapsUrl: _asString(json['maps_url']),
        whatsapp: _asString(json['whatsapp']),
      );

  final String address;
  final String openHours;
  final String mapsUrl;
  final String whatsapp;
}

class ServiceItem {
  const ServiceItem({
    required this.id,
    required this.name,
    required this.type,
    required this.unit,
    required this.basePrice,
    required this.description,
  });

  factory ServiceItem.fromJson(JsonMap json) => ServiceItem(
        id: _asInt(json['id']),
        name: _asString(json['name'], fallback: 'Layanan Younz'),
        type: _asString(json['type'], fallback: 'layanan'),
        unit: _asString(json['unit'], fallback: 'unit'),
        basePrice: _asDoubleOrNull(json['base_price']) ?? 0,
        description: _asString(
          json['description'],
          fallback:
              'Layanan profesional yang disesuaikan dengan kebutuhan Anda.',
        ),
      );

  final int id;
  final String name;
  final String type;
  final String unit;
  final double basePrice;
  final String description;
}

class FeaturedProduct {
  const FeaturedProduct({
    required this.id,
    required this.name,
    required this.unit,
    required this.price,
    required this.stock,
    required this.category,
  });

  factory FeaturedProduct.fromJson(JsonMap json) {
    final category = _asMap(json['category']);
    return FeaturedProduct(
      id: _asInt(json['id']),
      name: _asString(json['name']),
      unit: _asString(json['unit'], fallback: 'pcs'),
      price: _asDoubleOrNull(json['selling_price']) ?? 0,
      stock: _asInt(json['stock']),
      category: _asString(category['name'], fallback: 'Perlengkapan'),
    );
  }

  final int id;
  final String name;
  final String unit;
  final double price;
  final int stock;
  final String category;
}

class SiteContent {
  const SiteContent({
    required this.services,
    required this.products,
    required this.store,
  });

  factory SiteContent.fromJson(JsonMap json) => SiteContent(
        services: _asMapList(json['services'])
            .map(ServiceItem.fromJson)
            .toList(growable: false),
        products: _asMapList(json['featured_products'])
            .map(FeaturedProduct.fromJson)
            .toList(growable: false),
        store: StoreInfo.fromJson(_asMap(json['store'])),
      );

  final List<ServiceItem> services;
  final List<FeaturedProduct> products;
  final StoreInfo store;
}

class DigitalVariant {
  const DigitalVariant({
    required this.id,
    required this.label,
    required this.price,
    required this.priceLabel,
    required this.stockLabel,
    this.stock,
    this.sortOrder = 0,
  });

  factory DigitalVariant.fromJson(JsonMap json) => DigitalVariant(
        id: _asInt(json['id']),
        label: _asString(json['label']),
        price: _asDoubleOrNull(json['price']),
        priceLabel:
            _asString(json['price_label'], fallback: 'Konfirmasi harga'),
        stock: _asIntOrNull(json['stock']),
        stockLabel: _asString(json['stock_label'], fallback: 'Tersedia'),
        sortOrder: _asInt(json['sort_order']),
      );

  final int id;
  final String label;
  final double? price;
  final String priceLabel;
  final int? stock;
  final String stockLabel;
  final int sortOrder;
}

class DigitalProduct {
  const DigitalProduct({
    required this.id,
    required this.name,
    required this.category,
    required this.mark,
    required this.imageUrl,
    required this.price,
    required this.priceLabel,
    required this.stockLabel,
    required this.description,
    required this.variants,
    this.stock,
    this.sortOrder = 0,
  });

  factory DigitalProduct.fromJson(JsonMap json) => DigitalProduct(
        id: _asInt(json['id']),
        name: _asString(json['name']),
        category: _asString(json['category'], fallback: 'Digital'),
        mark: _asString(json['mark'], fallback: 'YD'),
        imageUrl: _asString(json['image_url']),
        price: _asDoubleOrNull(json['price']),
        priceLabel:
            _asString(json['price_label'], fallback: 'Konfirmasi harga'),
        stock: _asIntOrNull(json['stock']),
        stockLabel: _asString(json['stock_label'], fallback: 'Tersedia'),
        description: _asString(json['description']),
        sortOrder: _asInt(json['sort_order']),
        variants: _asMapList(json['variants'])
            .map(DigitalVariant.fromJson)
            .toList(growable: false),
      );

  final int id;
  final String name;
  final String category;
  final String mark;
  final String imageUrl;
  final double? price;
  final String priceLabel;
  final int? stock;
  final String stockLabel;
  final String description;
  final int sortOrder;
  final List<DigitalVariant> variants;
}

class TopupProduct {
  const TopupProduct({
    required this.id,
    required this.transactionType,
    required this.name,
    required this.category,
    required this.brand,
    required this.description,
    required this.price,
    required this.formType,
  });

  factory TopupProduct.fromJson(JsonMap json) => TopupProduct(
        id: _asInt(json['id']),
        transactionType:
            _asString(json['transaction_type'], fallback: 'prepaid'),
        name: _asString(json['product_name']),
        category: _asString(json['category'], fallback: 'Digital'),
        brand: _asString(json['brand'], fallback: 'Younz'),
        description: _asString(json['description']),
        price: _asDoubleOrNull(json['selling_price']) ?? 0,
        formType: _asString(json['form_type'], fallback: 'general'),
      );

  final int id;
  final String transactionType;
  final String name;
  final String category;
  final String brand;
  final String description;
  final double price;
  final String formType;
}

class TopupCatalog {
  const TopupCatalog({
    required this.products,
    required this.categories,
    required this.brands,
    required this.modeLabel,
    required this.integrationsReady,
  });

  factory TopupCatalog.fromJson(JsonMap json) {
    final mode = _asMap(json['mode']);
    return TopupCatalog(
      products: _asMapList(json['products'])
          .map(TopupProduct.fromJson)
          .toList(growable: false),
      categories: (json['categories'] as List? ?? const [])
          .map((item) => item.toString())
          .toList(growable: false),
      brands: (json['brands'] as List? ?? const [])
          .map((item) => item.toString())
          .toList(growable: false),
      modeLabel: _asString(mode['label'], fallback: 'Prabayar'),
      integrationsReady: json['integrations_ready'] == true,
    );
  }

  final List<TopupProduct> products;
  final List<String> categories;
  final List<String> brands;
  final String modeLabel;
  final bool integrationsReady;
}

class OrderSummary {
  const OrderSummary({
    required this.id,
    required this.orderNumber,
    required this.type,
    required this.service,
    required this.statusCode,
    required this.statusLabel,
    required this.estimatedPrice,
    required this.finalPrice,
    required this.paidAmount,
    required this.deadlineAt,
    required this.createdAt,
  });

  factory OrderSummary.fromJson(JsonMap json) {
    final status = _asMap(json['status']);
    return OrderSummary(
      id: _asInt(json['id']),
      orderNumber: _asString(json['order_number']),
      type: _asString(json['type'], fallback: 'Layanan'),
      service: _asString(json['service']),
      statusCode: _asString(status['code'], fallback: 'pending'),
      statusLabel: _asString(status['label'], fallback: 'Menunggu'),
      estimatedPrice: _asDoubleOrNull(json['estimated_price']),
      finalPrice: _asDoubleOrNull(json['final_price']),
      paidAmount: _asDoubleOrNull(json['paid_amount']) ?? 0,
      deadlineAt: json['deadline_at']?.toString(),
      createdAt: json['created_at']?.toString(),
    );
  }

  final int id;
  final String orderNumber;
  final String type;
  final String service;
  final String statusCode;
  final String statusLabel;
  final double? estimatedPrice;
  final double? finalPrice;
  final double paidAmount;
  final String? deadlineAt;
  final String? createdAt;
}

class PortalData {
  const PortalData({
    required this.name,
    required this.email,
    required this.phone,
    required this.totalOrders,
    required this.activeOrders,
    required this.completedOrders,
    required this.orders,
  });

  factory PortalData.fromJson(JsonMap json) {
    final user = _asMap(json['user']);
    final customer = _asMap(json['customer']);
    final summary = _asMap(json['summary']);
    return PortalData(
      name: _asString(customer['name'], fallback: _asString(user['name'])),
      email: _asString(customer['email'], fallback: _asString(user['email'])),
      phone: _asString(customer['phone'], fallback: _asString(user['phone'])),
      totalOrders: _asInt(summary['total']),
      activeOrders: _asInt(summary['active']),
      completedOrders: _asInt(summary['completed']),
      orders: _asMapList(json['orders'])
          .map(OrderSummary.fromJson)
          .toList(growable: false),
    );
  }

  final String name;
  final String email;
  final String phone;
  final int totalOrders;
  final int activeOrders;
  final int completedOrders;
  final List<OrderSummary> orders;
}

class DigitalProductOrderCheckout {
  const DigitalProductOrderCheckout({
    required this.id,
    required this.orderNumber,
    required this.message,
    required this.paymentRequired,
    required this.paymentStatus,
    required this.amount,
    required this.redirectUrl,
    this.trackingToken,
  });

  factory DigitalProductOrderCheckout.fromJson(JsonMap payload) {
    final data = _asMap(payload['data']);
    final payment = _asMap(payload['payment']);
    return DigitalProductOrderCheckout(
      id: _asInt(data['id']),
      orderNumber: _asString(data['order_number']),
      message: _asString(
        payload['message'],
        fallback: 'Pesanan produk berhasil dibuat.',
      ),
      paymentRequired: payment['required'] == true,
      paymentStatus: _asString(payment['status'], fallback: 'unpaid'),
      amount: _asIntOrNull(payment['amount']),
      redirectUrl: payment['redirect_url']?.toString(),
      trackingToken: payload['tracking_token']?.toString(),
    );
  }

  final int id;
  final String orderNumber;
  final String message;
  final bool paymentRequired;
  final String paymentStatus;
  final int? amount;
  final String? redirectUrl;
  final String? trackingToken;

  bool get isPaid => paymentStatus == 'paid';
}

class DigitalProductOrderStatus {
  const DigitalProductOrderStatus({
    required this.id,
    required this.orderNumber,
    required this.productName,
    required this.variantLabel,
    required this.quantity,
    required this.paymentStatus,
    required this.paymentTerminal,
    required this.amount,
    required this.paidAmount,
    required this.redirectUrl,
    required this.orderStatusCode,
    required this.orderStatusLabel,
  });

  factory DigitalProductOrderStatus.fromJson(JsonMap json) {
    final orderStatus = _asMap(json['order_status']);
    return DigitalProductOrderStatus(
      id: _asInt(json['id']),
      orderNumber: _asString(json['order_number']),
      productName: _asString(json['product_name'], fallback: 'Produk Digital'),
      variantLabel: json['variant_label']?.toString(),
      quantity: _asInt(json['quantity'], fallback: 1),
      paymentStatus: _asString(json['payment_status'], fallback: 'pending'),
      paymentTerminal: json['payment_terminal'] == true,
      amount: _asIntOrNull(json['amount']),
      paidAmount: _asInt(json['paid_amount']),
      redirectUrl: json['redirect_url']?.toString(),
      orderStatusCode: _asString(orderStatus['code'], fallback: 'pending'),
      orderStatusLabel:
          _asString(orderStatus['label'], fallback: 'Menunggu pembayaran'),
    );
  }

  final int id;
  final String orderNumber;
  final String productName;
  final String? variantLabel;
  final int quantity;
  final String paymentStatus;
  final bool paymentTerminal;
  final int? amount;
  final int paidAmount;
  final String? redirectUrl;
  final String orderStatusCode;
  final String orderStatusLabel;

  bool get isPaid => paymentStatus == 'paid';
}

class ApiResult {
  const ApiResult({
    required this.message,
    required this.data,
    this.trackingToken,
    this.redirectUrl,
  });

  final String message;
  final JsonMap data;
  final String? trackingToken;
  final String? redirectUrl;
}

class ChatReply {
  const ChatReply({required this.message, required this.sources});

  final String message;
  final List<String> sources;
}
