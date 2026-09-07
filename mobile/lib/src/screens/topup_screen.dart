import 'package:flutter/material.dart';

import '../api_client.dart';
import '../models.dart';
import '../theme.dart';
import '../widgets.dart';
import 'midtrans_payment_screen.dart';

class TopupScreen extends StatefulWidget {
  const TopupScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<TopupScreen> createState() => _TopupScreenState();
}

class _TopupScreenState extends State<TopupScreen> {
  final _search = TextEditingController();
  String _mode = 'prepaid';
  String _query = '';
  late Future<TopupCatalog> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.api.fetchTopupCatalog(mode: _mode);
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  void _changeMode(String value) {
    if (_mode == value) return;
    setState(() {
      _mode = value;
      _future = widget.api.fetchTopupCatalog(mode: value);
    });
  }

  void _reload() =>
      setState(() => _future = widget.api.fetchTopupCatalog(mode: _mode));

  void _clearSearch() {
    _search.clear();
    setState(() => _query = '');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: YounzColors.paper,
      body: CustomScrollView(
        slivers: [
          const SliverToBoxAdapter(child: YounzTopBar(title: 'Top Up')),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 26, 20, 0),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Top Up Instan',
                    style: Theme.of(context).textTheme.headlineLarge,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Isi ulang cepat dan aman.',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 22),
                  // Kept as a compact eyebrow for existing deep-link copy.
                  Text(
                    'Isi ulang. Bayar. Selesai.',
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          color: YounzColors.primary,
                        ),
                  ),
                  const SizedBox(height: 10),
                  _ModeSwitch(value: _mode, onChanged: _changeMode),
                  const SizedBox(height: 18),
                  TextField(
                    controller: _search,
                    onChanged: (value) => setState(() => _query = value),
                    decoration: InputDecoration(
                      hintText: 'Cari produk atau layanan...',
                      prefixIcon: const Icon(Icons.search_rounded),
                      suffixIcon: _query.isEmpty
                          ? null
                          : IconButton(
                              tooltip: 'Hapus pencarian',
                              onPressed: _clearSearch,
                              icon: const Icon(Icons.close_rounded),
                            ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 132),
            sliver: SliverToBoxAdapter(
              child: FutureBuilder<TopupCatalog>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return const SizedBox(height: 330, child: LoadingState());
                  }
                  if (snapshot.hasError || snapshot.data == null) {
                    return SizedBox(
                      height: 350,
                      child: ErrorState(
                        message: snapshot.error.toString(),
                        onRetry: _reload,
                      ),
                    );
                  }
                  final catalog = snapshot.data!;
                  final query = _query.trim().toLowerCase();
                  final products = catalog.products.where((product) {
                    if (query.isEmpty) return true;
                    return '${product.name} ${product.brand} ${product.category}'
                        .toLowerCase()
                        .contains(query);
                  }).toList(growable: false);
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (!catalog.integrationsReady) ...[
                        const SizedBox(height: 14),
                        const YounzNotice(
                          title: 'Checkout belum aktif',
                          message:
                              'Katalog tetap dapat dilihat. Pembayaran aktif setelah konfigurasi backend selesai.',
                          icon: Icons.construction_rounded,
                        ),
                      ],
                      const SizedBox(height: 18),
                      _TopupCategories(
                        categories: {
                          'Semua',
                          ...catalog.categories,
                          ...catalog.brands,
                        }.toList(growable: false),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'Pilih Nominal',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: 10),
                      if (products.isEmpty)
                        EmptyState(
                          title: 'Produk tidak ditemukan',
                          message:
                              'Coba kata kunci lain atau pindah mode transaksi.',
                          icon: Icons.search_off_rounded,
                          action: OutlinedButton.icon(
                            onPressed: _clearSearch,
                            icon: const Icon(Icons.refresh_rounded),
                            label: const Text('Reset pencarian'),
                          ),
                        )
                      else
                        LayoutBuilder(
                          builder: (context, constraints) {
                            final width = (constraints.maxWidth - 12) / 2;
                            final scale =
                                MediaQuery.textScalerOf(context).scale(14) / 14;
                            return Wrap(
                              spacing: 12,
                              runSpacing: 12,
                              children: products.indexed.map((entry) {
                                return SizedBox(
                                  width: width,
                                  child: _TopupProductCard(
                                    product: entry.$2,
                                    index: entry.$1,
                                    compact: scale <= 1.4,
                                    onTap: () => _openCheckout(entry.$2),
                                  ),
                                );
                              }).toList(growable: false),
                            );
                          },
                        ),
                    ],
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openCheckout(TopupProduct product) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _TopupCheckoutSheet(api: widget.api, product: product),
    );
  }
}

class _ModeSwitch extends StatelessWidget {
  const _ModeSwitch({required this.value, required this.onChanged});

  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: YounzColors.line),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          _ModeButton(
            label: 'Prabayar',
            icon: Icons.bolt_rounded,
            selected: value == 'prepaid',
            onTap: () => onChanged('prepaid'),
          ),
          const SizedBox(width: 6),
          _ModeButton(
            label: 'Pascabayar',
            icon: Icons.receipt_long_rounded,
            selected: value == 'postpaid',
            onTap: () => onChanged('postpaid'),
          ),
        ],
      ),
    );
  }
}

class _ModeButton extends StatelessWidget {
  const _ModeButton({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Semantics(
        button: true,
        selected: selected,
        label: label,
        excludeSemantics: true,
        onTap: onTap,
        child: Material(
          color: selected ? YounzColors.ink : Colors.transparent,
          borderRadius: BorderRadius.circular(9),
          child: InkWell(
            borderRadius: BorderRadius.circular(9),
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 11),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    icon,
                    size: 19,
                    color: selected ? Colors.white : YounzColors.muted,
                  ),
                  const SizedBox(width: 7),
                  Flexible(
                    child: Text(
                      label,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            color: selected ? Colors.white : YounzColors.ink,
                          ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _TopupCategories extends StatelessWidget {
  const _TopupCategories({required this.categories});

  final List<String> categories;

  IconData _iconFor(String value) {
    final label = value.toLowerCase();
    if (label.contains('data') || label.contains('internet')) {
      return Icons.wifi_rounded;
    }
    if (label.contains('wallet') || label.contains('uang')) {
      return Icons.account_balance_wallet_outlined;
    }
    if (label.contains('game')) return Icons.sports_esports_outlined;
    if (label.contains('listrik') || label.contains('pln')) {
      return Icons.bolt_rounded;
    }
    return Icons.phone_android_rounded;
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: categories.take(8).toList().indexed.map((entry) {
          final selected = entry.$1 == 0;
          final label = entry.$2;
          return Container(
            margin: const EdgeInsets.only(right: 9),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: selected ? YounzColors.brandLime : Colors.white,
              borderRadius: BorderRadius.circular(999),
              border: Border.all(
                color: selected
                    ? const Color(0xFF91C300)
                    : YounzColors.controlLine,
              ),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(_iconFor(label), size: 18),
                const SizedBox(width: 7),
                Text(label, style: Theme.of(context).textTheme.labelMedium),
              ],
            ),
          );
        }).toList(growable: false),
      ),
    );
  }
}

class _TopupProductCard extends StatelessWidget {
  const _TopupProductCard({
    required this.product,
    required this.index,
    required this.compact,
    required this.onTap,
  });

  final TopupProduct product;
  final int index;
  final bool compact;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      onTap: onTap,
      padding: const EdgeInsets.all(14),
      radius: 12,
      shadow: index == 0,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  product.brand.toUpperCase(),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: YounzColors.primary,
                      ),
                ),
              ),
              Icon(
                index == 1 ? Icons.check_circle_rounded : Icons.circle_outlined,
                size: 19,
                color: index == 1
                    ? YounzColors.brandLime
                    : YounzColors.controlLine,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            product.name,
            maxLines: compact ? 2 : 4,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          Text(
            formatRupiah(product.price),
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: YounzColors.primary,
                ),
          ),
          const SizedBox(height: 10),
          Text(
            product.category,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

class _TopupCheckoutSheet extends StatefulWidget {
  const _TopupCheckoutSheet({required this.api, required this.product});

  final ApiClient api;
  final TopupProduct product;

  @override
  State<_TopupCheckoutSheet> createState() => _TopupCheckoutSheetState();
}

class _TopupCheckoutSheetState extends State<_TopupCheckoutSheet> {
  final _formKey = GlobalKey<FormState>();
  final _destination = TextEditingController();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  bool _pending = false;

  String get destinationLabel {
    switch (widget.product.formType) {
      case 'pln_token':
        return 'Nomor meter / ID pelanggan PLN';
      case 'postpaid':
        return 'Nomor pelanggan';
      case 'mobile':
        return 'Nomor HP tujuan';
      default:
        return 'Nomor atau ID tujuan';
    }
  }

  @override
  void dispose() {
    _destination.dispose();
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _pending) return;
    setState(() => _pending = true);
    try {
      final result = await widget.api.createTopup(
        product: widget.product,
        destination: _destination.text,
        customerName: _name.text,
        customerEmail: _email.text,
        customerPhone: _phone.text,
      );
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (_) => _TopupResult(result: result),
      );
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error.message),
          backgroundColor: YounzColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _pending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return YounzSheet(
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * .78,
        ),
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Eyebrow('Checkout aman'),
                    const Spacer(),
                    IconButton(
                      tooltip: 'Tutup',
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                YounzSurface(
                  tone: YounzSurfaceTone.ink,
                  child: Row(
                    children: [
                      const YounzIconBadge(
                        icon: Icons.bolt_rounded,
                        background: YounzColors.lime,
                        foreground: YounzColors.ink,
                        size: 50,
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.product.name,
                              style: Theme.of(context)
                                  .textTheme
                                  .titleLarge
                                  ?.copyWith(color: Colors.white),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              formatRupiah(widget.product.price),
                              style: const TextStyle(
                                color: YounzColors.lime,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  'Tujuan transaksi',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _destination,
                  decoration: InputDecoration(
                    labelText: destinationLabel,
                    prefixIcon: const Icon(Icons.pin_outlined),
                  ),
                  validator: (value) => value == null || value.trim().length < 4
                      ? 'Masukkan nomor atau ID tujuan.'
                      : null,
                ),
                const SizedBox(height: 18),
                Text(
                  'Data pembeli',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _name,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                    labelText: 'Nama lengkap',
                    prefixIcon: Icon(Icons.person_outline_rounded),
                  ),
                  validator: (value) => value == null || value.trim().length < 2
                      ? 'Masukkan nama lengkap.'
                      : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                    labelText: 'Email',
                    prefixIcon: Icon(Icons.email_outlined),
                  ),
                  validator: (value) => value == null || !value.contains('@')
                      ? 'Masukkan email yang valid.'
                      : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Nomor WhatsApp',
                    prefixIcon: Icon(Icons.chat_outlined),
                  ),
                  validator: (value) =>
                      (value?.replaceAll(RegExp(r'\D'), '').length ?? 0) < 9
                          ? 'Periksa nomor WhatsApp.'
                          : null,
                ),
                const SizedBox(height: 14),
                const YounzNotice(
                  title: 'Periksa sekali lagi',
                  message:
                      'Transaksi digital tidak dapat dibatalkan setelah diproses ke nomor tujuan.',
                  icon: Icons.fact_check_outlined,
                  tone: YounzSurfaceTone.lime,
                ),
                const SizedBox(height: 16),
                ElevatedButton.icon(
                  onPressed: _pending ? null : _submit,
                  icon: _pending
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.lock_outline_rounded),
                  label: Text(
                    _pending
                        ? 'Menyiapkan transaksi...'
                        : 'Lanjutkan ke pembayaran',
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _TopupResult extends StatelessWidget {
  const _TopupResult({required this.result});

  final ApiResult result;

  @override
  Widget build(BuildContext context) {
    final orderNumber = result.data['order_number']?.toString() ?? '-';
    return YounzSheet(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const YounzIconBadge(
            icon: Icons.bolt_rounded,
            background: YounzColors.lime,
            foreground: YounzColors.ink,
            size: 56,
          ),
          const SizedBox(height: 18),
          Text(
            'Transaksi siap dibayar.',
            style: Theme.of(context).textTheme.headlineMedium,
          ),
          const SizedBox(height: 8),
          Text(result.message),
          const SizedBox(height: 18),
          YounzSurface(
            tone: YounzSurfaceTone.ink,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Eyebrow('Nomor transaksi', light: true),
                const SizedBox(height: 10),
                SelectableText(
                  orderNumber,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        color: Colors.white,
                      ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          if (result.redirectUrl?.isNotEmpty == true) ...[
            AccentButton(
              label: 'Bayar di aplikasi',
              icon: Icons.lock_outline_rounded,
              expand: true,
              onPressed: () async {
                await Navigator.of(context).push<void>(
                  MaterialPageRoute(
                    builder: (_) => MidtransPaymentScreen(
                      paymentUrl: result.redirectUrl!,
                      orderNumber: orderNumber,
                    ),
                  ),
                );
              },
            ),
            const SizedBox(height: 8),
          ],
          OutlinedButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Tutup'),
          ),
        ],
      ),
    );
  }
}
