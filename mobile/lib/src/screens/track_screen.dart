import 'package:flutter/material.dart';

import '../api_client.dart';
import '../models.dart';
import '../theme.dart';
import '../widgets.dart';

class TrackScreen extends StatefulWidget {
  const TrackScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<TrackScreen> createState() => _TrackScreenState();
}

class _TrackScreenState extends State<TrackScreen> {
  final _formKey = GlobalKey<FormState>();
  final _orderNumber = TextEditingController();
  final _phone = TextEditingController();
  OrderSummary? _order;
  String? _message;
  bool _pending = false;

  @override
  void dispose() {
    _orderNumber.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _track() async {
    if (!_formKey.currentState!.validate() || _pending) return;
    setState(() {
      _pending = true;
      _message = null;
      _order = null;
    });
    try {
      final result = await widget.api.trackOrder(
        orderNumber: _orderNumber.text,
        phone: _phone.text,
      );
      if (!mounted) return;
      setState(() => _order = OrderSummary.fromJson(result.data));
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _message = error.message);
    } finally {
      if (mounted) setState(() => _pending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: YounzColors.paper,
      body: CustomScrollView(
        slivers: [
          const SliverToBoxAdapter(child: YounzTopBar(title: 'Pelacakan')),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 48, 20, 132),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Lacak Pesanan',
                    style: Theme.of(context).textTheme.headlineLarge,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Masukkan detail pesanan Anda untuk melihat status terkini.',
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                  const SizedBox(height: 30),
                  _TrackingForm(
                    formKey: _formKey,
                    orderNumber: _orderNumber,
                    phone: _phone,
                    pending: _pending,
                    onSubmit: _track,
                  ),
                  if (_message != null) ...[
                    const SizedBox(height: 12),
                    _TrackingError(message: _message!),
                  ],
                  if (_order != null) ...[
                    const SizedBox(height: 22),
                    _OrderStatusCard(order: _order!),
                  ],
                  const SizedBox(height: 36),
                  const _TrackingGuide(),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TrackingForm extends StatelessWidget {
  const _TrackingForm({
    required this.formKey,
    required this.orderNumber,
    required this.phone,
    required this.pending,
    required this.onSubmit,
  });

  final GlobalKey<FormState> formKey;
  final TextEditingController orderNumber;
  final TextEditingController phone;
  final bool pending;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      shadow: true,
      padding: const EdgeInsets.all(20),
      radius: 14,
      child: Form(
        key: formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Nomor Pesanan',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: orderNumber,
              textCapitalization: TextCapitalization.characters,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(
                hintText: 'Contoh: YNZ-12345',
                prefixIcon: Icon(Icons.receipt_long_outlined),
              ),
              validator: (value) => value == null || value.trim().length < 6
                  ? 'Masukkan nomor pesanan.'
                  : null,
            ),
            const SizedBox(height: 20),
            Text(
              'Nomor WhatsApp',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: phone,
              keyboardType: TextInputType.phone,
              onFieldSubmitted: (_) => onSubmit(),
              decoration: const InputDecoration(
                hintText: '0812xxxxxxxx',
                prefixIcon: Icon(Icons.chat_outlined),
              ),
              validator: (value) =>
                  (value?.replaceAll(RegExp(r'\D'), '').length ?? 0) < 9
                      ? 'Periksa nomor WhatsApp.'
                      : null,
            ),
            const SizedBox(height: 22),
            ElevatedButton.icon(
              onPressed: pending ? null : onSubmit,
              icon: pending
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.search_rounded),
              label: Text(pending ? 'MEMERIKSA...' : 'LACAK SEKARANG'),
            ),
          ],
        ),
      ),
    );
  }
}

class _TrackingError extends StatelessWidget {
  const _TrackingError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      padding: const EdgeInsets.all(15),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const YounzIconBadge(
            icon: Icons.error_outline_rounded,
            background: YounzColors.dangerWash,
            foreground: YounzColors.danger,
            size: 40,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Pesanan belum ditemukan',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const SizedBox(height: 3),
                Text(message),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _OrderStatusCard extends StatelessWidget {
  const _OrderStatusCard({required this.order});

  final OrderSummary order;

  @override
  Widget build(BuildContext context) {
    final completed = order.statusCode == 'completed';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SectionHeading(
          eyebrow: 'Hasil pelacakan',
          title: 'Pesanan ditemukan.',
        ),
        const SizedBox(height: 16),
        YounzSurface(
          padding: EdgeInsets.zero,
          radius: 14,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TechnicalPattern(
                style: TechnicalPatternStyle.grid,
                color: YounzColors.outline,
                opacity: .09,
                spacing: 22,
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'STATUS PESANAN',
                                  style: Theme.of(context).textTheme.labelSmall,
                                ),
                                const SizedBox(height: 4),
                                SelectableText(
                                  order.orderNumber,
                                  style:
                                      Theme.of(context).textTheme.headlineSmall,
                                ),
                              ],
                            ),
                          ),
                          StatusPill(order.statusLabel, good: completed),
                        ],
                      ),
                      const SizedBox(height: 14),
                      const Divider(color: YounzColors.primary),
                      const SizedBox(height: 18),
                      _LiveTimeline(order: order),
                    ],
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.all(18),
                color: Colors.white,
                child: Column(
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: _OrderFact(
                            label: 'Estimasi',
                            value: formatRupiah(order.estimatedPrice),
                            icon: Icons.calculate_outlined,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: _OrderFact(
                            label: 'Harga akhir',
                            value: formatRupiah(order.finalPrice),
                            icon: Icons.sell_outlined,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: _OrderFact(
                            label: 'Terbayar',
                            value: formatRupiah(order.paidAmount),
                            icon: Icons.payments_outlined,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: _OrderFact(
                            label: 'Deadline',
                            value: prettyDate(order.deadlineAt),
                            icon: Icons.event_outlined,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _LiveTimeline extends StatelessWidget {
  const _LiveTimeline({required this.order});

  final OrderSummary order;

  @override
  Widget build(BuildContext context) {
    final completed = order.statusCode == 'completed';
    final active = !completed;
    final steps = [
      ('Diterima', true, prettyDate(order.createdAt)),
      (
        order.statusLabel,
        active || completed,
        'Tim kami sedang menangani pesanan Anda.'
      ),
      (
        'Selesai',
        completed,
        completed ? 'Pesanan telah selesai.' : 'Menunggu penyelesaian.'
      ),
    ];
    return Column(
      children: steps.indexed.map((entry) {
        final step = entry.$2;
        final last = entry.$1 == steps.length - 1;
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Column(
              children: [
                Container(
                  width: 28,
                  height: 28,
                  decoration: BoxDecoration(
                    color: step.$2 ? YounzColors.primary : Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: step.$2 ? YounzColors.primary : YounzColors.line,
                      width: 2,
                    ),
                  ),
                  child: step.$2
                      ? const Icon(Icons.check_rounded,
                          color: Colors.white, size: 17)
                      : null,
                ),
                if (!last)
                  Container(
                    width: 2,
                    height: 54,
                    color: step.$2 ? YounzColors.primary : YounzColors.line,
                  ),
              ],
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Padding(
                padding: EdgeInsets.only(bottom: last ? 0 : 18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      step.$1,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: step.$2
                                ? YounzColors.primary
                                : YounzColors.outline,
                          ),
                    ),
                    const SizedBox(height: 3),
                    Text(step.$3, style: Theme.of(context).textTheme.bodySmall),
                  ],
                ),
              ),
            ),
          ],
        );
      }).toList(growable: false),
    );
  }
}

class _OrderFact extends StatelessWidget {
  const _OrderFact({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 112,
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: YounzColors.paper,
        borderRadius: BorderRadius.circular(YounzRadii.md),
        border: Border.all(color: YounzColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: YounzColors.blue, size: 20),
          const Spacer(),
          Text(label, style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 2),
          Text(
            value,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleSmall,
          ),
        ],
      ),
    );
  }
}

class _TrackingGuide extends StatelessWidget {
  const _TrackingGuide();

  @override
  Widget build(BuildContext context) {
    const steps = [
      ('Diterima', 'Brief dan file masuk ke sistem.'),
      ('Diperiksa', 'Detail, harga, dan estimasi dikonfirmasi.'),
      ('Diproses', 'Pekerjaan berjalan sesuai kesepakatan.'),
      ('Selesai', 'Hasil siap diambil atau dikirim.'),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SectionHeading(
          eyebrow: 'Status map',
          title: 'Empat tahap sampai selesai.',
          description:
              'Status berubah setelah tim menyelesaikan setiap checkpoint.',
        ),
        const SizedBox(height: 18),
        YounzSurface(
          tone: YounzSurfaceTone.soft,
          padding: const EdgeInsets.all(18),
          child: Column(
            children: steps.indexed.map((entry) {
              final last = entry.$1 == steps.length - 1;
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Column(
                    children: [
                      Container(
                        width: 38,
                        height: 38,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: entry.$1 == 0
                              ? YounzColors.blue
                              : entry.$1 == steps.length - 1
                                  ? YounzColors.lime
                                  : Colors.white,
                          borderRadius: BorderRadius.circular(13),
                          border: Border.all(color: YounzColors.line),
                        ),
                        child: Text(
                          '${entry.$1 + 1}',
                          style: TextStyle(
                            color:
                                entry.$1 == 0 ? Colors.white : YounzColors.ink,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      if (!last)
                        Container(
                            width: 2, height: 46, color: YounzColors.line),
                    ],
                  ),
                  const SizedBox(width: 13),
                  Expanded(
                    child: Padding(
                      padding: EdgeInsets.only(top: 2, bottom: last ? 0 : 20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            entry.$2.$1,
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: 3),
                          Text(entry.$2.$2),
                        ],
                      ),
                    ),
                  ),
                ],
              );
            }).toList(),
          ),
        ),
      ],
    );
  }
}
