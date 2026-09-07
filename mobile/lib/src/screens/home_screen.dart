import 'package:flutter/material.dart';

import '../api_client.dart';
import '../models.dart';
import '../navigation.dart';
import '../product_artwork.dart';
import '../theme.dart';
import '../widgets.dart';
import 'ai_chat_screen.dart';
import 'product_detail_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.api, required this.onNavigate});

  final ApiClient api;
  final ValueChanged<int> onNavigate;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late Future<SiteContent> _siteFuture;
  late Future<List<DigitalProduct>> _digitalFuture;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _siteFuture = widget.api.fetchSite();
    _digitalFuture = widget.api.fetchDigitalProducts();
  }

  Future<void> _refresh() async {
    setState(_reload);
    try {
      await Future.wait([_siteFuture, _digitalFuture]);
    } catch (_) {
      // Each section keeps its own useful fallback while offline.
    }
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _refresh,
      color: YounzColors.primary,
      child: CustomScrollView(
        key: const PageStorageKey('home-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(
            child: YounzTopBar(title: 'Beranda'),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 36, 20, 0),
            sliver: SliverToBoxAdapter(
              child: _HomeHero(onNavigate: widget.onNavigate),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 32, 20, 0),
            sliver: SliverToBoxAdapter(
              child: _QuickGrid(onNavigate: widget.onNavigate),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 36, 20, 0),
            sliver: SliverToBoxAdapter(
              child: FutureBuilder<List<DigitalProduct>>(
                future: _digitalFuture,
                builder: (context, snapshot) => _FavoriteShelf(
                  products: snapshot.data ?? const [],
                  api: widget.api,
                  loading: snapshot.connectionState != ConnectionState.done,
                  onOpenCatalog: () =>
                      widget.onNavigate(YounzDestination.products),
                ),
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 46, 20, 0),
            sliver: SliverToBoxAdapter(
              child: _AiCard(
                onOpen: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => AiChatScreen(api: widget.api),
                  ),
                ),
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 34, 20, 132),
            sliver: SliverToBoxAdapter(
              child: FutureBuilder<SiteContent>(
                future: _siteFuture,
                builder: (context, snapshot) => _StoreInformation(
                  store: snapshot.data?.store,
                  onOrder: () => widget.onNavigate(YounzDestination.services),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HomeHero extends StatelessWidget {
  const _HomeHero({required this.onNavigate});

  final ValueChanged<int> onNavigate;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: YounzColors.blue,
        borderRadius: BorderRadius.circular(24),
        boxShadow: YounzElevation.surface,
      ),
      clipBehavior: Clip.antiAlias,
      child: TechnicalPattern(
        color: Colors.white,
        opacity: .055,
        spacing: 18,
        child: Stack(
          children: [
            Positioned(
              right: -48,
              top: -52,
              child: Container(
                width: 160,
                height: 160,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white12, width: 22),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 24, 24, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: .08),
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(color: Colors.white24),
                    ),
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const CircleAvatar(
                            radius: 4,
                            backgroundColor: YounzColors.brandLime,
                          ),
                          const SizedBox(width: 8),
                          Text(
                            'OPEN • SAMPIT',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              letterSpacing: .55,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 34),
                  Text(
                    'Dari ide ke hasil,\ntanpa pindah-pindah\ntempat.',
                    style: Theme.of(context).textTheme.displaySmall?.copyWith(
                          color: Colors.white,
                          fontSize: 35,
                          height: 1.08,
                          letterSpacing: -1.35,
                        ),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    'Layanan terpadu untuk printing, desain, website, aplikasi, hingga top up digital.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Colors.white.withValues(alpha: .9),
                        ),
                  ),
                  const SizedBox(height: 26),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Expanded(
                        child: AccentButton(
                          label: 'Mulai brief',
                          icon: Icons.arrow_forward_rounded,
                          expand: true,
                          onPressed: () =>
                              onNavigate(YounzDestination.services),
                        ),
                      ),
                      const SizedBox(width: 12),
                      IconButton(
                        tooltip: 'Top up cepat',
                        onPressed: () => onNavigate(YounzDestination.topup),
                        style: IconButton.styleFrom(
                          minimumSize: const Size(54, 54),
                          foregroundColor: Colors.white,
                          backgroundColor: Colors.white.withValues(alpha: .1),
                          side: const BorderSide(color: Colors.white24),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                        ),
                        icon: const Icon(Icons.bolt_rounded),
                      ),
                    ],
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

class _QuickGrid extends StatelessWidget {
  const _QuickGrid({required this.onNavigate});

  final ValueChanged<int> onNavigate;

  @override
  Widget build(BuildContext context) {
    const items = [
      ('Pesan\nlayanan', Icons.edit_note_rounded, YounzDestination.services),
      (
        'Produk\ndigital',
        Icons.shopping_bag_outlined,
        YounzDestination.products
      ),
      ('Top up\ncepat', Icons.speed_rounded, YounzDestination.topup),
      ('Lacak\norder', Icons.local_shipping_outlined, YounzDestination.track),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final itemWidth = (constraints.maxWidth - 12) / 2;
        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: items.indexed.map((entry) {
            final item = entry.$2;
            return SizedBox(
              width: itemWidth,
              child: _QuickCard(
                label: item.$1,
                icon: item.$2,
                patterned: entry.$1 == 3,
                onTap: () => onNavigate(item.$3),
              ),
            );
          }).toList(growable: false),
        );
      },
    );
  }
}

class _QuickCard extends StatelessWidget {
  const _QuickCard({
    required this.label,
    required this.icon,
    required this.patterned,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool patterned;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final content = Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: patterned ? YounzColors.limeWash : YounzColors.blueWash,
              shape: BoxShape.circle,
            ),
            child: Icon(
              icon,
              color: patterned ? YounzColors.green : YounzColors.primary,
            ),
          ),
          const SizedBox(height: 25),
          Text(
            label,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontSize: 20,
                  height: 1.08,
                ),
          ),
        ],
      ),
    );
    return Semantics(
      button: true,
      label: label.replaceAll('\n', ' '),
      child: Material(
        color: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: YounzColors.controlLine),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 150),
            child: patterned
                ? TechnicalPattern(
                    opacity: .07,
                    spacing: 14,
                    child: content,
                  )
                : content,
          ),
        ),
      ),
    );
  }
}

class _FavoriteShelf extends StatelessWidget {
  const _FavoriteShelf({
    required this.products,
    required this.api,
    required this.loading,
    required this.onOpenCatalog,
  });

  final List<DigitalProduct> products;
  final ApiClient api;
  final bool loading;
  final VoidCallback onOpenCatalog;

  @override
  Widget build(BuildContext context) {
    final visible = products.take(5).toList(growable: false);
    final scale = MediaQuery.textScalerOf(context).scale(14) / 14;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Pilihan Favorit',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
            ),
            TextButton(
              onPressed: onOpenCatalog,
              style: TextButton.styleFrom(
                padding: EdgeInsets.zero,
                minimumSize: const Size(44, 44),
              ),
              child: const FittedBox(
                fit: BoxFit.scaleDown,
                child: Text('Lihat semua'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        if (loading)
          const SizedBox(
            height: 220,
            child: LoadingState(label: 'Memuat pilihan favorit...'),
          )
        else if (visible.isEmpty)
          YounzNotice(
            title: 'Katalog sedang diperbarui',
            message: 'Buka menu Produk untuk melihat pilihan terbaru.',
            action: IconButton(
              onPressed: onOpenCatalog,
              icon: const Icon(Icons.arrow_forward_rounded),
            ),
          )
        else
          SizedBox(
            height: 235 + ((scale - 1).clamp(0, 1) * 180),
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              clipBehavior: Clip.none,
              itemCount: visible.length,
              separatorBuilder: (_, __) => const SizedBox(width: 12),
              itemBuilder: (context, index) {
                final product = visible[index];
                return SizedBox(
                  width: 200,
                  child: Material(
                    color: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                      side: const BorderSide(color: YounzColors.controlLine),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: InkWell(
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => ProductDetailScreen(
                            api: api,
                            productId: product.id,
                            initialProduct: product,
                          ),
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          DigitalProductArtwork(
                            product: product,
                            api: api,
                            aspectRatio: 1.75,
                            borderRadius: BorderRadius.zero,
                          ),
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    product.category.toUpperCase(),
                                    style:
                                        Theme.of(context).textTheme.labelSmall,
                                  ),
                                  const SizedBox(height: 5),
                                  Text(
                                    product.name,
                                    maxLines: scale > 1.4 ? 3 : 2,
                                    overflow: TextOverflow.ellipsis,
                                    style:
                                        Theme.of(context).textTheme.titleMedium,
                                  ),
                                  const Spacer(),
                                  Text(
                                    product.priceLabel,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleSmall
                                        ?.copyWith(color: YounzColors.primary),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
      ],
    );
  }
}

class _AiCard extends StatelessWidget {
  const _AiCard({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      tone: YounzSurfaceTone.ink,
      padding: const EdgeInsets.all(24),
      radius: 24,
      border: false,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .09),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: Colors.white24),
                ),
                child:
                    const Icon(Icons.smart_toy_outlined, color: Colors.white),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Konsultasi AI',
                      style: Theme.of(context)
                          .textTheme
                          .titleLarge
                          ?.copyWith(color: Colors.white),
                    ),
                    Text(
                      'Bingung mulai dari mana?',
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
          const SizedBox(height: 22),
          OutlinedButton.icon(
            onPressed: onOpen,
            iconAlignment: IconAlignment.end,
            icon: const Icon(Icons.arrow_outward_rounded),
            label: const Text('Tanya Younz AI'),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(52),
              foregroundColor: Colors.white,
              side: const BorderSide(color: Colors.white38),
            ),
          ),
        ],
      ),
    );
  }
}

class _StoreInformation extends StatelessWidget {
  const _StoreInformation({required this.store, required this.onOrder});

  final StoreInfo? store;
  final VoidCallback onOrder;

  @override
  Widget build(BuildContext context) {
    final info = store;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Informasi Toko',
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        const SizedBox(height: 14),
        YounzSurface(
          padding: const EdgeInsets.all(18),
          radius: 16,
          child: Column(
            children: [
              _StoreRow(
                icon: Icons.location_on_outlined,
                title: 'Lokasi',
                value: info?.address ?? 'Younz Digital Center, Sampit',
              ),
              const Divider(height: 28),
              _StoreRow(
                icon: Icons.schedule_rounded,
                title: 'Jam operasional',
                value: info?.openHours ?? 'Senin - Sabtu, 09.00 - 17.00',
              ),
              const SizedBox(height: 18),
              FilledButton.icon(
                onPressed: onOrder,
                icon: const Icon(Icons.arrow_forward_rounded),
                label: const Text('Buat pesanan'),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _StoreRow extends StatelessWidget {
  const _StoreRow({
    required this.icon,
    required this.title,
    required this.value,
  });

  final IconData icon;
  final String title;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: YounzColors.outline),
        const SizedBox(width: 13),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: Theme.of(context).textTheme.labelMedium),
              const SizedBox(height: 3),
              Text(value),
            ],
          ),
        ),
      ],
    );
  }
}
