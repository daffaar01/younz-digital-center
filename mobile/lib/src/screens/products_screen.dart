import 'package:flutter/material.dart';

import '../api_client.dart';
import '../models.dart';
import '../product_artwork.dart';
import '../product_support.dart';
import '../theme.dart';
import '../widgets.dart';
import 'product_detail_screen.dart';

const _preferredCategories = [
  'Semua',
  'AI Assistant',
  'AI Kreatif',
  'Streaming',
  'Komunitas',
];

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({
    super.key,
    required this.api,
  });

  final ApiClient api;

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  final _searchController = TextEditingController();
  late Future<List<DigitalProduct>> _future;
  String _category = 'Semua';
  String _query = '';

  @override
  void initState() {
    super.initState();
    _future = widget.api.fetchDigitalProducts();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _reload() => setState(() => _future = widget.api.fetchDigitalProducts());

  Future<void> _refresh() async {
    final next = widget.api.fetchDigitalProducts();
    setState(() => _future = next);
    try {
      await next;
    } catch (_) {
      // The error state below provides the retry action.
    }
  }

  void _clearSearch() {
    _searchController.clear();
    setState(() => _query = '');
  }

  void _openProduct(DigitalProduct product) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ProductDetailScreen(
          api: widget.api,
          productId: product.id,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: YounzColors.paper,
      body: RefreshIndicator(
        onRefresh: _refresh,
        color: YounzColors.primary,
        backgroundColor: Colors.white,
        child: CustomScrollView(
          key: const PageStorageKey('products-catalog-scroll'),
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            const SliverToBoxAdapter(child: YounzTopBar(title: 'Produk')),
            SliverPadding(
              padding: EdgeInsets.zero,
              sliver: SliverToBoxAdapter(
                child: _ProductsHero(
                  controller: _searchController,
                  query: _query,
                  onChanged: (value) => setState(() => _query = value),
                  onClear: _clearSearch,
                ),
              ),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(12, 16, 12, 132),
              sliver: SliverToBoxAdapter(
                child: FutureBuilder<List<DigitalProduct>>(
                  future: _future,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState != ConnectionState.done) {
                      return const SizedBox(
                        height: 360,
                        child: LoadingState(label: 'Memuat katalog produk...'),
                      );
                    }
                    if (snapshot.hasError) {
                      return SizedBox(
                        height: 380,
                        child: ErrorState(
                          message: snapshot.error.toString(),
                          onRetry: _reload,
                        ),
                      );
                    }

                    final products = [...?snapshot.data]..sort((a, b) {
                        final order = a.sortOrder.compareTo(b.sortOrder);
                        return order == 0 ? a.id.compareTo(b.id) : order;
                      });
                    final categories = _categoriesFor(products);
                    final visible = _visibleProducts(products);

                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _CategoryFilters(
                          categories: categories,
                          selected: _category,
                          onSelected: (value) =>
                              setState(() => _category = value),
                        ),
                        const SizedBox(height: 14),
                        if (_query.trim().isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: Text(
                              '${visible.length} hasil untuk "${_query.trim()}"',
                              style: Theme.of(context).textTheme.labelMedium,
                            ),
                          ),
                        if (visible.isEmpty)
                          EmptyState(
                            title: products.isEmpty
                                ? 'Belum ada produk aktif'
                                : 'Produk tidak ditemukan',
                            message: products.isEmpty
                                ? 'Katalog sedang kosong. Muat ulang untuk mengambil data terbaru dari server.'
                                : 'Coba kategori atau kata kunci lain, lalu muat ulang katalog bila diperlukan.',
                            icon: Icons.inventory_2_outlined,
                            action: DarkButton(
                              label: 'Muat ulang katalog',
                              icon: Icons.refresh_rounded,
                              onPressed: _reload,
                            ),
                          )
                        else
                          ...visible.indexed.map((entry) => Padding(
                                padding: const EdgeInsets.only(bottom: 8),
                                child: _ProductCatalogCard(
                                  product: entry.$2,
                                  api: widget.api,
                                  featured: entry.$1 == 0,
                                  onTap: () => _openProduct(entry.$2),
                                ),
                              )),
                        if (visible.isNotEmpty) ...[
                          const SizedBox(height: 22),
                          _CatalogHelp(onPressed: _reload),
                        ],
                      ],
                    );
                  },
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<DigitalProduct> _visibleProducts(List<DigitalProduct> products) {
    final query = _query.trim().toLowerCase();
    return products.where((product) {
      final categoryMatches =
          _category == 'Semua' || product.category == _category;
      final queryMatches = query.isEmpty ||
          '${product.name} ${product.category} ${product.description}'
              .toLowerCase()
              .contains(query);
      return categoryMatches && queryMatches;
    }).toList(growable: false);
  }
}

List<String> _categoriesFor(List<DigitalProduct> products) {
  final values = products.map((product) => product.category).toSet();
  final ordered = _preferredCategories.where(
    (category) => category == 'Semua' || values.contains(category),
  );
  final known = _preferredCategories.toSet();
  final extras = values.where((category) => !known.contains(category)).toList()
    ..sort();
  return [...ordered, ...extras];
}

class _ProductsHero extends StatelessWidget {
  const _ProductsHero({
    required this.controller,
    required this.query,
    required this.onChanged,
    required this.onClear,
  });

  final TextEditingController controller;
  final String query;
  final ValueChanged<String> onChanged;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: YounzColors.blue,
      child: TechnicalPattern(
        color: Colors.white,
        opacity: .07,
        spacing: 15,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 18, 12, 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'DIGITAL SHELF',
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: Colors.white,
                      letterSpacing: 1.2,
                    ),
              ),
              const SizedBox(height: 8),
              Text(
                'Pilih akses. Tentukan varian. Pesan langsung.',
                style: Theme.of(context).textTheme.displaySmall?.copyWith(
                      color: Colors.white,
                      fontSize: 34,
                      height: 1.02,
                    ),
              ),
              const SizedBox(height: 16),
              TextField(
                key: const ValueKey('product-search-field'),
                controller: controller,
                textInputAction: TextInputAction.search,
                onChanged: onChanged,
                decoration: InputDecoration(
                  hintText: 'Cari ChatGPT, streaming, komunitas...',
                  prefixIcon: const Icon(Icons.search_rounded),
                  suffixIcon: query.isEmpty
                      ? null
                      : IconButton(
                          tooltip: 'Hapus pencarian',
                          onPressed: onClear,
                          icon: const Icon(Icons.close_rounded),
                        ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(999),
                    borderSide: BorderSide.none,
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(999),
                    borderSide: const BorderSide(
                      color: YounzColors.brandLime,
                      width: 2,
                    ),
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

class _CategoryFilters extends StatelessWidget {
  const _CategoryFilters({
    required this.categories,
    required this.selected,
    required this.onSelected,
  });

  final List<String> categories;
  final String selected;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: categories.map((category) {
          final active = category == selected;
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: ChoiceChip(
              key: ValueKey('product-category-$category'),
              label: Text(category),
              selected: active,
              showCheckmark: false,
              onSelected: (_) => onSelected(category),
              selectedColor: YounzColors.ink,
              labelStyle: TextStyle(
                color: active ? Colors.white : YounzColors.ink,
                fontWeight: FontWeight.w700,
                fontSize: 11,
              ),
              side: BorderSide(
                color: active ? YounzColors.ink : YounzColors.controlLine,
              ),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(999),
              ),
            ),
          );
        }).toList(growable: false),
      ),
    );
  }
}

class _ProductCatalogCard extends StatelessWidget {
  const _ProductCatalogCard({
    required this.product,
    required this.api,
    required this.featured,
    required this.onTap,
  });

  final DigitalProduct product;
  final ApiClient api;
  final bool featured;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final available = digitalProductIsAvailable(product);
    return Semantics(
      button: true,
      label: 'Lihat detail ${product.name}',
      child: YounzSurface(
        onTap: onTap,
        padding: EdgeInsets.zero,
        radius: 10,
        shadow: featured,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                DigitalProductArtwork(
                  product: product,
                  api: api,
                  aspectRatio: 2.25,
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(10),
                  ),
                ),
                Positioned(
                  left: 8,
                  top: 8,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 7,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: YounzColors.ink,
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      product.category,
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                            color: Colors.white,
                            fontSize: 9,
                          ),
                    ),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 10, 8, 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          product.name,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 3),
                        Row(
                          children: [
                            CircleAvatar(
                              radius: 3,
                              backgroundColor: available
                                  ? YounzColors.brandLime
                                  : YounzColors.danger,
                            ),
                            const SizedBox(width: 5),
                            Expanded(
                              child: Text(
                                product.stockLabel,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: Theme.of(context)
                                    .textTheme
                                    .labelSmall
                                    ?.copyWith(fontSize: 9),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Mulai dari',
                          style: Theme.of(context)
                              .textTheme
                              .labelSmall
                              ?.copyWith(fontSize: 9),
                        ),
                        Text(
                          product.priceLabel,
                          style: Theme.of(context)
                              .textTheme
                              .titleMedium
                              ?.copyWith(color: YounzColors.primary),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    width: 38,
                    height: 38,
                    decoration: const BoxDecoration(
                      color: Color(0xFFF1EDEC),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.arrow_forward_rounded, size: 19),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CatalogHelp extends StatelessWidget {
  const _CatalogHelp({required this.onPressed});

  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      tone: YounzSurfaceTone.ink,
      padding: const EdgeInsets.all(22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const YounzIconBadge(
            icon: Icons.forum_rounded,
            background: YounzColors.lime,
            foreground: YounzColors.ink,
          ),
          const SizedBox(height: 18),
          Text(
            'Katalog selalu mengikuti server.',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: Colors.white,
                ),
          ),
          const SizedBox(height: 8),
          Text(
            'Muat ulang untuk mengambil harga, varian, dan stok terbaru sebelum membuat pesanan.',
            style: Theme.of(context)
                .textTheme
                .bodyMedium
                ?.copyWith(color: Colors.white70),
          ),
          const SizedBox(height: 18),
          AccentButton(
            label: 'Muat ulang katalog',
            icon: Icons.refresh_rounded,
            onPressed: onPressed,
          ),
        ],
      ),
    );
  }
}
