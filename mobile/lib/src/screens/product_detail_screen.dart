import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../api_client.dart';
import '../models.dart';
import '../product_artwork.dart';
import '../theme.dart';
import '../widgets.dart';
import 'midtrans_payment_screen.dart';

typedef DigitalProductPaymentLauncher = Future<void> Function(
  BuildContext context,
  DigitalProductOrderCheckout checkout,
);

class ProductDetailScreen extends StatefulWidget {
  const ProductDetailScreen({
    super.key,
    required this.api,
    required this.productId,
    this.initialProduct,
    this.paymentLauncher,
  });

  final ApiClient api;
  final int productId;
  final DigitalProduct? initialProduct;
  final DigitalProductPaymentLauncher? paymentLauncher;

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  late Future<DigitalProduct> _future;
  late Future<List<DigitalProduct>> _relatedFuture;
  int? _selectedVariantId;
  int _quantity = 1;
  bool _checkoutPending = false;
  String? _checkoutIdempotencyKey;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.api.fetchDigitalProduct(widget.productId);
    _relatedFuture = widget.api.fetchDigitalProducts();
  }

  void _retry() => setState(_load);

  DigitalVariant? _selectedVariant(DigitalProduct product) {
    for (final variant in product.variants) {
      if (variant.id == _selectedVariantId) return variant;
    }
    return null;
  }

  void _selectVariant(DigitalVariant variant) {
    setState(() {
      _selectedVariantId = variant.id;
      _quantity = _quantity.clamp(1, _maximumQuantity(variant.stock)).toInt();
      _checkoutIdempotencyKey = null;
    });
  }

  int _maximumQuantity(int? stock) {
    if (stock == 0) return 1;
    if (stock != null && stock > 0) return stock;
    return 99;
  }

  void _changeQuantity(int delta, int? stock) {
    final maximum = _maximumQuantity(stock);
    setState(() {
      _quantity = (_quantity + delta).clamp(1, maximum).toInt();
      _checkoutIdempotencyKey = null;
    });
  }

  Future<void> _submitOrder(
    DigitalProduct product,
    DigitalVariant? selected,
  ) async {
    if (_checkoutPending) return;
    final unitPrice = selected?.price ?? product.price;
    final confirmed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _ProductCheckoutSheet(
        product: product,
        variant: selected,
        quantity: _quantity,
        unitPrice: unitPrice,
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _checkoutPending = true);
    _checkoutIdempotencyKey ??= widget.api.createIdempotencyKey();
    try {
      final checkout = await widget.api.createDigitalProductOrder(
        productId: product.id,
        variantId: selected?.id,
        quantity: _quantity,
        idempotencyKey: _checkoutIdempotencyKey!,
      );
      if (!mounted) return;
      _checkoutIdempotencyKey = null;
      if (checkout.redirectUrl?.isNotEmpty == true) {
        final launcher = widget.paymentLauncher ?? _openMidtransPayment;
        await launcher(context, checkout);
      } else {
        await showDialog<void>(
          context: context,
          builder: (_) => _OrderCreatedDialog(checkout: checkout),
        );
      }
      if (mounted) _retry();
    } on ApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error.message),
          backgroundColor: YounzColors.danger,
        ),
      );
      if (error.statusCode == 409 || error.statusCode == 422) {
        _checkoutIdempotencyKey = null;
        _retry();
      }
    } finally {
      if (mounted) setState(() => _checkoutPending = false);
    }
  }

  Future<void> _openMidtransPayment(
    BuildContext context,
    DigitalProductOrderCheckout checkout,
  ) async {
    await Navigator.of(context).push<DigitalProductOrderStatus>(
      MaterialPageRoute(
        builder: (_) => MidtransPaymentScreen(
          paymentUrl: checkout.redirectUrl!,
          orderNumber: checkout.orderNumber,
          completionHost: Uri.tryParse(widget.api.baseUrl)?.host,
          statusLoader: () =>
              widget.api.fetchDigitalProductOrderStatus(checkout.id),
          statusRefresher: () =>
              widget.api.refreshDigitalProductOrderPayment(checkout.id),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<DigitalProduct>(
      future: _future,
      initialData: widget.initialProduct,
      builder: (context, snapshot) {
        final product = snapshot.data;
        if (product == null &&
            snapshot.connectionState != ConnectionState.done) {
          return _DetailFrame(
            child: const LoadingState(label: 'Memuat detail produk...'),
          );
        }
        if (product == null) {
          return _DetailFrame(
            child: ErrorState(
              message: snapshot.error?.toString() ??
                  'Produk tidak ditemukan atau sudah tidak aktif.',
              onRetry: _retry,
            ),
          );
        }

        final selected = _selectedVariant(product);
        final needsVariant = product.variants.isNotEmpty && selected == null;
        final stock =
            product.variants.isNotEmpty ? selected?.stock : product.stock;
        final stockLabel = product.variants.isNotEmpty
            ? selected?.stockLabel ?? 'Pilih varian untuk melihat stok'
            : product.stockLabel;
        final priceLabel = product.variants.isNotEmpty
            ? selected?.priceLabel ?? 'Pilih varian'
            : product.priceLabel;
        final unitPrice = selected?.price ?? product.price;
        final outOfStock = stock == 0;
        final compactWidth = MediaQuery.sizeOf(context).width <= 380;

        return Scaffold(
          backgroundColor: YounzColors.paper,
          appBar: AppBar(
            leading: IconButton(
              tooltip: 'Kembali ke katalog',
              onPressed: () => Navigator.of(context).maybePop(),
              icon: const Icon(Icons.arrow_back_rounded),
            ),
            title: const Text('Detail Produk'),
            actions: [
              IconButton(
                tooltip: 'Bagikan produk',
                onPressed: () async {
                  await Clipboard.setData(
                    ClipboardData(text: product.name),
                  );
                  if (!context.mounted) return;
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Nama produk disalin.')),
                  );
                },
                icon: const Icon(Icons.share_outlined),
              ),
              const SizedBox(width: 8),
            ],
          ),
          body: CustomScrollView(
            key: PageStorageKey('product-detail-${product.id}'),
            slivers: [
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(0, 0, 0, 34),
                sliver: SliverToBoxAdapter(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Stack(
                        clipBehavior: Clip.none,
                        children: [
                          DigitalProductArtwork(
                            product: product,
                            api: widget.api,
                            aspectRatio: compactWidth ? 2.2 : 1.65,
                            borderRadius: BorderRadius.zero,
                          ),
                          Positioned(
                            left: 22,
                            right: 22,
                            bottom: -40,
                            child: YounzSurface(
                              padding: const EdgeInsets.all(14),
                              radius: 18,
                              shadow: true,
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      const Icon(
                                        Icons.smart_toy_outlined,
                                        color: YounzColors.primary,
                                        size: 20,
                                      ),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: Text(
                                          product.category.toUpperCase(),
                                          style: Theme.of(context)
                                              .textTheme
                                              .labelMedium
                                              ?.copyWith(
                                                color: YounzColors.primary,
                                              ),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    product.name,
                                    style: Theme.of(context)
                                        .textTheme
                                        .headlineMedium,
                                  ),
                                  if (product.description
                                      .trim()
                                      .isNotEmpty) ...[
                                    const SizedBox(height: 8),
                                    Text(
                                      product.description,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodyMedium,
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 50),
                      Padding(
                        padding: const EdgeInsets.fromLTRB(22, 0, 22, 0),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (snapshot.hasError) ...[
                              YounzNotice(
                                title: 'Menampilkan data tersimpan',
                                message:
                                    'Pembaruan detail belum tersambung. Coba lagi nanti.',
                                icon: Icons.cloud_off_rounded,
                                action: IconButton(
                                  tooltip: 'Muat ulang detail',
                                  onPressed: _retry,
                                  icon: const Icon(Icons.refresh_rounded),
                                ),
                              ),
                              const SizedBox(height: 18),
                            ],
                            _PricePanel(
                              priceLabel: priceLabel,
                              stockLabel: stockLabel,
                              needsVariant: needsVariant,
                              quantity: _quantity,
                              unitPrice: unitPrice,
                            ),
                            if (product.variants.isNotEmpty) ...[
                              const SizedBox(height: 30),
                              _VariantSelector(
                                variants: product.variants,
                                selectedId: _selectedVariantId,
                                onSelected: _selectVariant,
                              ),
                            ],
                            const SizedBox(height: 26),
                            _QuantityPanel(
                              quantity: _quantity,
                              stock: stock,
                              enabled: !needsVariant && !outOfStock,
                              onDecrease: () => _changeQuantity(-1, stock),
                              onIncrease: () => _changeQuantity(1, stock),
                            ),
                            const SizedBox(height: 34),
                            _ProductInformation(
                              product: product,
                              priceLabel: priceLabel,
                              stockLabel: stockLabel,
                            ),
                            const SizedBox(height: 28),
                            const _ServiceAssurance(),
                            const SizedBox(height: 34),
                            _RelatedProducts(
                              future: _relatedFuture,
                              current: product,
                              api: widget.api,
                              paymentLauncher: widget.paymentLauncher,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          bottomNavigationBar: _OrderBar(
            priceLabel: unitPrice == null
                ? priceLabel
                : formatRupiah(unitPrice * _quantity),
            supportingLabel: needsVariant
                ? 'Pilih varian untuk melanjutkan'
                : outOfStock
                    ? stockLabel
                    : selected?.label ?? stockLabel,
            buttonLabel: needsVariant
                ? 'Pilih varian dahulu'
                : outOfStock
                    ? 'Stok habis'
                    : _checkoutPending
                        ? 'Menyiapkan pembayaran...'
                        : unitPrice == null
                            ? 'Kirim permintaan'
                            : 'Bayar Midtrans',
            buttonIcon: outOfStock
                ? Icons.inventory_2_outlined
                : unitPrice == null
                    ? Icons.receipt_long_outlined
                    : Icons.lock_outline_rounded,
            accent: false,
            onPressed: needsVariant || outOfStock || _checkoutPending
                ? null
                : () => _submitOrder(product, selected),
          ),
        );
      },
    );
  }
}

class _ProductCheckoutSheet extends StatelessWidget {
  const _ProductCheckoutSheet({
    required this.product,
    required this.variant,
    required this.quantity,
    required this.unitPrice,
  });

  final DigitalProduct product;
  final DigitalVariant? variant;
  final int quantity;
  final double? unitPrice;

  @override
  Widget build(BuildContext context) {
    return YounzSheet(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(child: Eyebrow('Konfirmasi checkout')),
              IconButton(
                tooltip: 'Tutup',
                onPressed: () => Navigator.of(context).pop(false),
                icon: const Icon(Icons.close_rounded),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            'Periksa pesanan sebelum lanjut.',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 16),
          YounzSurface(
            tone: YounzSurfaceTone.ink,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  product.name,
                  style: Theme.of(context)
                      .textTheme
                      .titleLarge
                      ?.copyWith(color: Colors.white),
                ),
                if (variant != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    variant!.label,
                    style: const TextStyle(color: Colors.white70),
                  ),
                ],
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '$quantity item',
                        style: const TextStyle(color: Colors.white70),
                      ),
                    ),
                    Flexible(
                      child: Text(
                        unitPrice == null
                            ? 'Konfirmasi harga'
                            : formatRupiah(unitPrice! * quantity),
                        textAlign: TextAlign.right,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context)
                            .textTheme
                            .titleLarge
                            ?.copyWith(color: YounzColors.lime),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          const YounzNotice(
            title: 'Checkout melalui akun',
            message:
                'Nama dan nomor kontak diambil dari akun yang sedang masuk. Harga dan stok diverifikasi ulang oleh server.',
            icon: Icons.verified_user_outlined,
          ),
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              key: const ValueKey('confirm-product-checkout'),
              onPressed: () => Navigator.of(context).pop(true),
              icon: Icon(
                unitPrice == null
                    ? Icons.receipt_long_outlined
                    : Icons.lock_outline_rounded,
              ),
              label: Text(
                unitPrice == null
                    ? 'Buat permintaan pesanan'
                    : 'Buat pesanan & bayar',
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _OrderCreatedDialog extends StatelessWidget {
  const _OrderCreatedDialog({required this.checkout});

  final DigitalProductOrderCheckout checkout;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      icon: const Icon(
        Icons.receipt_long_outlined,
        color: YounzColors.primary,
        size: 36,
      ),
      title: const Text('Pesanan berhasil dibuat'),
      content: Text(
        '${checkout.message}\n\nNomor pesanan: ${checkout.orderNumber}',
        textAlign: TextAlign.center,
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Selesai'),
        ),
      ],
    );
  }
}

class _DetailFrame extends StatelessWidget {
  const _DetailFrame({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: YounzColors.paper,
      appBar: AppBar(
        leading: IconButton(
          tooltip: 'Kembali ke katalog',
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: const Text('Detail produk'),
      ),
      body: child,
    );
  }
}

class _PricePanel extends StatelessWidget {
  const _PricePanel({
    required this.priceLabel,
    required this.stockLabel,
    required this.needsVariant,
    required this.quantity,
    required this.unitPrice,
  });

  final String priceLabel;
  final String stockLabel;
  final bool needsVariant;
  final int quantity;
  final double? unitPrice;

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      tone: YounzSurfaceTone.lime,
      border: false,
      padding: EdgeInsets.zero,
      radius: 14,
      child: TechnicalPattern(
        color: YounzColors.ink,
        opacity: .055,
        spacing: 14,
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: LayoutBuilder(
            builder: (context, constraints) {
              final largeText =
                  MediaQuery.textScalerOf(context).scale(14) / 14 > 1.4;
              final price = Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    needsVariant ? 'HARGA SESUAI VARIAN' : 'MULAI DARI',
                    style: Theme.of(context).textTheme.labelSmall,
                  ),
                  const SizedBox(height: 5),
                  Text(
                    priceLabel,
                    key: const ValueKey('product-effective-price'),
                    style: Theme.of(context).textTheme.headlineLarge,
                  ),
                ],
              );
              final stock = Container(
                key: const ValueKey('product-effective-stock'),
                padding:
                    const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
                decoration: BoxDecoration(
                  color: YounzColors.ink,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.bolt_rounded,
                      color: YounzColors.brandLime,
                      size: 17,
                    ),
                    const SizedBox(width: 6),
                    Flexible(
                      child: Text(
                        needsVariant ? 'Pilih varian' : stockLabel,
                        maxLines: largeText ? 2 : 1,
                        overflow: TextOverflow.ellipsis,
                        style:
                            Theme.of(context).textTheme.labelMedium?.copyWith(
                                  color: Colors.white,
                                ),
                      ),
                    ),
                  ],
                ),
              );
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (largeText) ...[
                    price,
                    const SizedBox(height: 12),
                    stock,
                  ] else
                    Row(
                      children: [
                        Expanded(child: price),
                        const SizedBox(width: 10),
                        Flexible(child: stock),
                      ],
                    ),
                  if (!needsVariant && unitPrice != null && quantity > 1) ...[
                    const SizedBox(height: 14),
                    const Divider(color: Color(0x33070707)),
                    const SizedBox(height: 10),
                    Text(
                      'Total ${_quantityLabel(quantity)}: ${formatRupiah(unitPrice! * quantity)}',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ],
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

String _quantityLabel(int quantity) => '$quantity item';

class _VariantSelector extends StatelessWidget {
  const _VariantSelector({
    required this.variants,
    required this.selectedId,
    required this.onSelected,
  });

  final List<DigitalVariant> variants;
  final int? selectedId;
  final ValueChanged<DigitalVariant> onSelected;

  @override
  Widget build(BuildContext context) {
    final sorted = [...variants]..sort((a, b) {
        final order = a.sortOrder.compareTo(b.sortOrder);
        return order == 0 ? a.id.compareTo(b.id) : order;
      });
    final largeText = MediaQuery.textScalerOf(context).scale(14) / 14 > 1.4;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Pilih Durasi',
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        const SizedBox(height: 16),
        ...sorted.map((variant) {
          final selected = selectedId == variant.id;
          final outOfStock = variant.stock == 0;
          return Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Semantics(
              button: true,
              selected: selected,
              label:
                  '${variant.label}, ${variant.priceLabel}, ${variant.stockLabel}',
              child: Material(
                color: selected ? const Color(0xFFF5F4FF) : Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                  side: BorderSide(
                    color:
                        selected ? YounzColors.blue : YounzColors.controlLine,
                    width: selected ? 2 : 1,
                  ),
                ),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  key: ValueKey('product-variant-${variant.id}'),
                  onTap: () => onSelected(variant),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Builder(
                      builder: (context) {
                        final title = Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              variant.label,
                              style: Theme.of(context).textTheme.titleLarge,
                            ),
                            const SizedBox(height: 7),
                            Text(
                              outOfStock
                                  ? 'Saat ini tidak tersedia'
                                  : 'Akses sesuai durasi pilihan',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        );
                        final price = Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              variant.priceLabel,
                              style: Theme.of(context)
                                  .textTheme
                                  .titleMedium
                                  ?.copyWith(
                                    color: selected
                                        ? YounzColors.primary
                                        : outOfStock
                                            ? YounzColors.outline
                                            : YounzColors.ink,
                                    decoration: outOfStock
                                        ? TextDecoration.lineThrough
                                        : null,
                                  ),
                            ),
                            const SizedBox(height: 9),
                            StatusPill(
                              variant.stockLabel,
                              good: !outOfStock,
                              danger: outOfStock,
                            ),
                          ],
                        );
                        if (largeText) {
                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              title,
                              const SizedBox(height: 12),
                              Align(
                                  alignment: Alignment.centerLeft,
                                  child: price),
                            ],
                          );
                        }
                        return Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(child: title),
                            const SizedBox(width: 12),
                            price,
                          ],
                        );
                      },
                    ),
                  ),
                ),
              ),
            ),
          );
        }),
      ],
    );
  }
}

class _QuantityPanel extends StatelessWidget {
  const _QuantityPanel({
    required this.quantity,
    required this.stock,
    required this.enabled,
    required this.onDecrease,
    required this.onIncrease,
  });

  final int quantity;
  final int? stock;
  final bool enabled;
  final VoidCallback onDecrease;
  final VoidCallback onIncrease;

  @override
  Widget build(BuildContext context) {
    final maximum = stock != null && stock! > 0 ? stock! : 99;
    return YounzSurface(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Kuantitas', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 5),
          Text(
            !enabled
                ? 'Pilih varian yang tersedia untuk mengatur jumlah.'
                : stock == null
                    ? 'Ketersediaan akhir dikonfirmasi operator.'
                    : '$stock item tersedia.',
          ),
          const SizedBox(height: 16),
          Semantics(
            container: true,
            label: 'Kuantitas $quantity',
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                _QuantityButton(
                  key: const ValueKey('quantity-decrease'),
                  label: 'Kurangi kuantitas',
                  icon: Icons.remove_rounded,
                  onPressed: enabled && quantity > 1 ? onDecrease : null,
                ),
                SizedBox(
                  width: 64,
                  child: Text(
                    '$quantity',
                    key: const ValueKey('product-quantity'),
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                _QuantityButton(
                  key: const ValueKey('quantity-increase'),
                  label: 'Tambah kuantitas',
                  icon: Icons.add_rounded,
                  onPressed: enabled && quantity < maximum ? onIncrease : null,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _QuantityButton extends StatelessWidget {
  const _QuantityButton({
    super.key,
    required this.label,
    required this.icon,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return IconButton.outlined(
      tooltip: label,
      onPressed: onPressed,
      style: IconButton.styleFrom(
        minimumSize: const Size(48, 48),
        foregroundColor: YounzColors.ink,
        disabledForegroundColor: YounzColors.muted,
        side: const BorderSide(color: YounzColors.controlLine),
      ),
      icon: Icon(icon),
    );
  }
}

class _ProductInformation extends StatelessWidget {
  const _ProductInformation({
    required this.product,
    required this.priceLabel,
    required this.stockLabel,
  });

  final DigitalProduct product;
  final String priceLabel;
  final String stockLabel;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SectionHeading(
          eyebrow: 'Informasi produk',
          title: 'Ringkasan sebelum memesan.',
          description:
              'Pesanan dibuat di server dan pembayaran berharga tetap berada di dalam aplikasi melalui Midtrans.',
        ),
        const SizedBox(height: 16),
        YounzSurface(
          child: Column(
            children: [
              _InformationRow(label: 'Kategori', value: product.category),
              const Divider(),
              _InformationRow(label: 'Harga', value: priceLabel),
              const Divider(),
              _InformationRow(label: 'Stok', value: stockLabel),
              const Divider(),
              const _InformationRow(
                label: 'Pembayaran',
                value: 'Midtrans di dalam aplikasi',
              ),
              const Divider(),
              const _InformationRow(
                label: 'Pengiriman',
                value: 'Digital / sesuai produk',
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _InformationRow extends StatelessWidget {
  const _InformationRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 104,
            child: Text(
              label,
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: Theme.of(context).textTheme.titleSmall,
            ),
          ),
        ],
      ),
    );
  }
}

class _ServiceAssurance extends StatelessWidget {
  const _ServiceAssurance();

  @override
  Widget build(BuildContext context) {
    const assurances = [
      (
        '01',
        'Harga dari server',
        'Harga, varian, kuantitas, dan stok diperiksa ulang saat checkout.',
      ),
      (
        '02',
        'Pembayaran aman',
        'Halaman Midtrans dibuka langsung di aplikasi tanpa browser eksternal.',
      ),
      (
        '03',
        'Tim langsung diberi tahu',
        'Setelah pembayaran terverifikasi, admin menerima notifikasi pesanan untuk dikirim.',
      ),
    ];
    return YounzSurface(
      tone: YounzSurfaceTone.ink,
      padding: const EdgeInsets.all(22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Jaminan layanan', light: true),
          const SizedBox(height: 18),
          ...assurances.map((item) => Padding(
                padding: const EdgeInsets.only(bottom: 18),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 42,
                      height: 42,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: YounzColors.lime,
                        borderRadius: BorderRadius.circular(YounzRadii.sm),
                      ),
                      child: Text(
                        item.$1,
                        style: const TextStyle(
                          color: YounzColors.ink,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item.$2,
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(color: Colors.white),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            item.$3,
                            style: Theme.of(context)
                                .textTheme
                                .bodyMedium
                                ?.copyWith(color: Colors.white70),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              )),
        ],
      ),
    );
  }
}

class _RelatedProducts extends StatelessWidget {
  const _RelatedProducts({
    required this.future,
    required this.current,
    required this.api,
    required this.paymentLauncher,
  });

  final Future<List<DigitalProduct>> future;
  final DigitalProduct current;
  final ApiClient api;
  final DigitalProductPaymentLauncher? paymentLauncher;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<DigitalProduct>>(
      future: future,
      builder: (context, snapshot) {
        if (!snapshot.hasData) return const SizedBox.shrink();
        final related =
            snapshot.data!.where((product) => product.id != current.id).toList()
              ..sort((a, b) {
                final aSame = a.category == current.category ? 0 : 1;
                final bSame = b.category == current.category ? 0 : 1;
                final categoryOrder = aSame.compareTo(bSame);
                return categoryOrder == 0
                    ? a.sortOrder.compareTo(b.sortOrder)
                    : categoryOrder;
              });
        final visible = related.take(3).toList(growable: false);
        if (visible.isEmpty) return const SizedBox.shrink();

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SectionHeading(
              eyebrow: 'Produk lainnya',
              title: 'Mungkin kamu juga mencari.',
            ),
            const SizedBox(height: 16),
            ...visible.map((product) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: YounzSurface(
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => ProductDetailScreen(
                          api: api,
                          productId: product.id,
                          initialProduct: product,
                          paymentLauncher: paymentLauncher,
                        ),
                      ),
                    ),
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      children: [
                        Container(
                          width: 48,
                          height: 48,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: YounzColors.blueWash,
                            borderRadius: BorderRadius.circular(YounzRadii.sm),
                          ),
                          child: Text(
                            product.mark,
                            style: const TextStyle(
                              color: YounzColors.blue,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                product.category.toUpperCase(),
                                style: Theme.of(context)
                                    .textTheme
                                    .labelSmall
                                    ?.copyWith(color: YounzColors.blue),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                product.name,
                                style: Theme.of(context).textTheme.titleMedium,
                              ),
                              Text(product.priceLabel),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        const Icon(Icons.arrow_forward_rounded),
                      ],
                    ),
                  ),
                )),
          ],
        );
      },
    );
  }
}

class _OrderBar extends StatelessWidget {
  const _OrderBar({
    required this.priceLabel,
    required this.supportingLabel,
    required this.buttonLabel,
    required this.buttonIcon,
    required this.accent,
    required this.onPressed,
  });

  final String priceLabel;
  final String supportingLabel;
  final String buttonLabel;
  final IconData buttonIcon;
  final bool accent;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      elevation: 12,
      shadowColor: YounzColors.ink.withValues(alpha: .16),
      child: SafeArea(
        top: false,
        minimum: const EdgeInsets.fromLTRB(18, 10, 18, 10),
        child: LayoutBuilder(
          builder: (context, constraints) {
            final compact = constraints.maxWidth < 350 ||
                MediaQuery.textScalerOf(context).scale(14) / 14 > 1.4;
            final button = FilledButton.icon(
              key: const ValueKey('product-order-button'),
              onPressed: onPressed,
              icon: Icon(buttonIcon, size: 18),
              label: Text(buttonLabel),
              style: FilledButton.styleFrom(
                backgroundColor:
                    accent ? YounzColors.brandLime : YounzColors.primary,
                foregroundColor: accent ? YounzColors.ink : Colors.white,
                minimumSize: const Size(0, 52),
                padding: const EdgeInsets.symmetric(horizontal: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            );
            final total = Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Total Harga',
                  style: Theme.of(context).textTheme.labelSmall,
                ),
                Text(
                  priceLabel,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        color: YounzColors.ink,
                      ),
                ),
                if (compact)
                  Text(
                    supportingLabel,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
              ],
            );
            if (compact) {
              return Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [total, const SizedBox(height: 8), button],
              );
            }
            return Row(
              children: [
                Expanded(child: total),
                const SizedBox(width: 12),
                Flexible(child: button),
              ],
            );
          },
        ),
      ),
    );
  }
}
