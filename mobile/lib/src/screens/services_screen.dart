import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api_client.dart';
import '../models.dart';
import '../theme.dart';
import '../widgets.dart';

class ServicesScreen extends StatefulWidget {
  const ServicesScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends State<ServicesScreen> {
  late Future<List<ServiceItem>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.api.fetchServices();
  }

  void _reload() => setState(() => _future = widget.api.fetchServices());

  void _openOrder(
      {ServiceItem? service, List<ServiceItem> services = const []}) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => OrderScreen(
          api: widget.api,
          initialService: service,
          services: services,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: YounzColors.paper,
      body: CustomScrollView(
        slivers: [
          const SliverToBoxAdapter(child: YounzTopBar(title: 'Layanan')),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 0),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Layanan\nProfesional',
                    style: Theme.of(context).textTheme.displaySmall?.copyWith(
                          fontSize: 38,
                          height: 1.02,
                        ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'Solusi digital terpadu untuk kebutuhan bisnis dan personal Anda. Cepat, tepat, dan premium.',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: _openOrder,
                    icon: const Icon(Icons.add_circle_outline_rounded),
                    label: const Text('Buat pesanan'),
                  ),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 132),
            sliver: SliverToBoxAdapter(
              child: FutureBuilder<List<ServiceItem>>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return const SizedBox(height: 320, child: LoadingState());
                  }
                  if (snapshot.hasError) {
                    return SizedBox(
                      height: 340,
                      child: ErrorState(
                        message: snapshot.error.toString(),
                        onRetry: _reload,
                      ),
                    );
                  }
                  final services = snapshot.data ?? const <ServiceItem>[];
                  if (services.isEmpty) {
                    return EmptyState(
                      title: 'Belum ada layanan aktif',
                      message:
                          'Kirim brief bebas dan operator akan mengarahkannya ke layanan yang tepat.',
                      icon: Icons.draw_outlined,
                      action: DarkButton(
                        label: 'Kirim brief bebas',
                        icon: Icons.add_rounded,
                        onPressed: _openOrder,
                      ),
                    );
                  }
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ...services.indexed.map(
                        (entry) => Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _ServiceCard(
                            service: entry.$2,
                            index: entry.$1,
                            onTap: () => _openOrder(
                              service: entry.$2,
                              services: services,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 6),
                      YounzNotice(
                        title: 'Tidak menemukan kategori yang pas?',
                        message:
                            'Pilih jenis terdekat atau kirim brief bebas. Operator akan membantu mengelompokkan pekerjaan.',
                        icon: Icons.route_rounded,
                        tone: YounzSurfaceTone.lime,
                        action: IconButton(
                          tooltip: 'Buat pesanan',
                          onPressed: _openOrder,
                          icon: const Icon(Icons.arrow_outward_rounded),
                        ),
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
}

class _ServiceCard extends StatelessWidget {
  const _ServiceCard({
    required this.service,
    required this.index,
    required this.onTap,
  });

  final ServiceItem service;
  final int index;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = index.isOdd ? YounzColors.brandLime : YounzColors.blueWash;
    final foreground = index.isOdd ? YounzColors.ink : YounzColors.primary;
    final card = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: accent,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(_serviceIcon(service.type), color: foreground, size: 21),
        ),
        const SizedBox(height: 16),
        Text(
          service.name,
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                fontSize: index % 3 == 2 ? 25 : 20,
                height: 1.08,
              ),
        ),
        const SizedBox(height: 8),
        Text(service.description, style: Theme.of(context).textTheme.bodySmall),
        const SizedBox(height: 16),
        const Divider(),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'MULAI DARI',
                    style: Theme.of(context).textTheme.labelSmall,
                  ),
                  const SizedBox(height: 3),
                  Text.rich(
                    TextSpan(
                      children: [
                        TextSpan(
                          text: formatRupiah(service.basePrice),
                          style: const TextStyle(
                            color: YounzColors.primary,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        TextSpan(text: '/${service.unit}'),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Container(
              width: 38,
              height: 38,
              decoration: const BoxDecoration(
                color: Color(0xFFF1EDEC),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.arrow_forward_rounded, size: 20),
            ),
          ],
        ),
      ],
    );
    return YounzSurface(
      onTap: onTap,
      padding: const EdgeInsets.all(16),
      radius: 12,
      shadow: index == 0,
      child: index.isOdd
          ? TechnicalPattern(opacity: .055, spacing: 15, child: card)
          : card,
    );
  }
}

IconData _serviceIcon(String type) {
  return switch (type.toLowerCase()) {
    'print' || 'fotokopi' => Icons.print_rounded,
    'scan' => Icons.document_scanner_rounded,
    'ketik' => Icons.keyboard_alt_rounded,
    'desain' => Icons.draw_rounded,
    'website' => Icons.language_rounded,
    'aplikasi' => Icons.phone_android_rounded,
    _ => Icons.auto_awesome_mosaic_rounded,
  };
}

class OrderScreen extends StatefulWidget {
  const OrderScreen({
    super.key,
    required this.api,
    this.initialService,
    this.services = const [],
  });

  final ApiClient api;
  final ServiceItem? initialService;
  final List<ServiceItem> services;

  @override
  State<OrderScreen> createState() => _OrderScreenState();
}

class _OrderScreenState extends State<OrderScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _notes = TextEditingController();
  late Future<List<ServiceItem>> _servicesFuture;
  int? _serviceId;
  String _type = 'print';
  String? _paperSize;
  String? _colorMode;
  File? _file;
  String? _fileName;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _serviceId = widget.initialService?.id;
    _type = widget.initialService?.type ?? 'print';
    _servicesFuture = widget.services.isNotEmpty
        ? Future.value(widget.services)
        : widget.api.fetchServices();
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'jpg',
        'jpeg',
        'png',
        'txt',
      ],
    );
    if (result == null || result.files.single.path == null) return;
    final selected = result.files.single;
    if (selected.size > 20 * 1024 * 1024) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ukuran file maksimal 20 MB.')),
      );
      return;
    }
    setState(() {
      _file = File(selected.path!);
      _fileName = selected.name;
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _submitting) return;
    setState(() => _submitting = true);
    try {
      final result = await widget.api.createServiceOrder(
        customerName: _name.text,
        customerPhone: _phone.text,
        type: _type,
        serviceId: _serviceId,
        paperSize: _paperSize,
        colorMode: _colorMode,
        notes: _notes.text,
        file: _file,
      );
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (_) => _OrderSuccess(result: result),
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
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    const types = [
      'print',
      'fotokopi',
      'scan',
      'ketik',
      'desain',
      'website',
      'aplikasi',
    ];
    return Scaffold(
      backgroundColor: YounzColors.paper,
      appBar: AppBar(
        leading: IconButton(
          onPressed: () => Navigator.of(context).pop(),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: const Text('Pesanan baru'),
      ),
      body: FutureBuilder<List<ServiceItem>>(
        future: _servicesFuture,
        builder: (context, snapshot) {
          final services = snapshot.data ?? widget.services;
          return SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 132),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const YounzPageHeader(
                    eyebrow: 'Brief pelanggan',
                    title: 'Ceritakan hasil yang Anda butuhkan.',
                    description:
                        'Isi yang sudah pasti. Bagian lain dapat dikonfirmasi bersama operator melalui WhatsApp.',
                    icon: Icons.edit_note_rounded,
                    tone: YounzSurfaceTone.blue,
                  ),
                  const SizedBox(height: 26),
                  const _FormProgress(),
                  const SizedBox(height: 18),
                  _OrderSection(
                    number: '01',
                    title: 'Kontak pemesan',
                    description: 'Dipakai untuk konfirmasi brief dan estimasi.',
                    child: Column(
                      children: [
                        TextFormField(
                          controller: _name,
                          textInputAction: TextInputAction.next,
                          decoration: const InputDecoration(
                            labelText: 'Nama lengkap',
                            prefixIcon: Icon(Icons.person_outline_rounded),
                          ),
                          validator: (value) =>
                              value == null || value.trim().length < 2
                                  ? 'Masukkan nama lengkap.'
                                  : null,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _phone,
                          keyboardType: TextInputType.phone,
                          textInputAction: TextInputAction.next,
                          decoration: const InputDecoration(
                            labelText: 'Nomor WhatsApp',
                            hintText: '0821...',
                            prefixIcon: Icon(Icons.chat_outlined),
                          ),
                          validator: (value) {
                            final digits =
                                value?.replaceAll(RegExp(r'\D'), '') ?? '';
                            return digits.length < 9
                                ? 'Periksa nomor WhatsApp.'
                                : null;
                          },
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  _OrderSection(
                    number: '02',
                    title: 'Jenis pekerjaan',
                    description:
                        'Pilih kategori terdekat. Operator tetap memeriksa kecocokannya.',
                    child: Column(
                      children: [
                        DropdownButtonFormField<String>(
                          key: ValueKey('type-$_type'),
                          initialValue: _type,
                          decoration: const InputDecoration(
                            labelText: 'Jenis layanan',
                            prefixIcon: Icon(Icons.category_outlined),
                          ),
                          items: types
                              .map(
                                (type) => DropdownMenuItem(
                                  value: type,
                                  child: Text(serviceTypeLabel(type)),
                                ),
                              )
                              .toList(),
                          onChanged: (value) =>
                              setState(() => _type = value ?? _type),
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<int>(
                          key: ValueKey('service-$_serviceId'),
                          initialValue:
                              services.any((item) => item.id == _serviceId)
                                  ? _serviceId
                                  : null,
                          decoration: const InputDecoration(
                            labelText: 'Paket layanan (opsional)',
                            prefixIcon: Icon(Icons.grid_view_rounded),
                          ),
                          items: services
                              .map(
                                (service) => DropdownMenuItem(
                                  value: service.id,
                                  child: Text(
                                    service.name,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              )
                              .toList(),
                          onChanged: (value) {
                            ServiceItem? service;
                            for (final item in services) {
                              if (item.id == value) {
                                service = item;
                                break;
                              }
                            }
                            setState(() {
                              _serviceId = value;
                              if (service != null) _type = service.type;
                            });
                          },
                        ),
                        const SizedBox(height: 12),
                        LayoutBuilder(
                          builder: (context, constraints) {
                            final compact = constraints.maxWidth < 330;
                            final paper = DropdownButtonFormField<String>(
                              key: ValueKey('paper-$_paperSize'),
                              initialValue: _paperSize,
                              decoration: const InputDecoration(
                                labelText: 'Ukuran kertas',
                              ),
                              items: const ['A4', 'F4', 'A3', 'A5']
                                  .map(
                                    (value) => DropdownMenuItem(
                                      value: value,
                                      child: Text(value),
                                    ),
                                  )
                                  .toList(),
                              onChanged: (value) =>
                                  setState(() => _paperSize = value),
                            );
                            final color = DropdownButtonFormField<String>(
                              key: ValueKey('color-$_colorMode'),
                              initialValue: _colorMode,
                              decoration: const InputDecoration(
                                labelText: 'Mode warna',
                              ),
                              items: const [
                                DropdownMenuItem(
                                  value: 'black_white',
                                  child: Text('Hitam putih'),
                                ),
                                DropdownMenuItem(
                                  value: 'color',
                                  child: Text('Warna'),
                                ),
                              ],
                              onChanged: (value) =>
                                  setState(() => _colorMode = value),
                            );
                            if (compact) {
                              return Column(
                                children: [
                                  paper,
                                  const SizedBox(height: 12),
                                  color,
                                ],
                              );
                            }
                            return Row(
                              children: [
                                Expanded(child: paper),
                                const SizedBox(width: 10),
                                Expanded(child: color),
                              ],
                            );
                          },
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  _OrderSection(
                    number: '03',
                    title: 'Detail dan file',
                    description:
                        'Semakin jelas hasil akhir yang diinginkan, semakin cepat estimasi dibuat.',
                    child: Column(
                      children: [
                        TextFormField(
                          controller: _notes,
                          minLines: 5,
                          maxLines: 8,
                          maxLength: 3000,
                          decoration: const InputDecoration(
                            labelText: 'Catatan dan spesifikasi',
                            alignLabelWithHint: true,
                            hintText:
                                'Jumlah, finishing, deadline, referensi, atau hasil akhir...',
                          ),
                        ),
                        const SizedBox(height: 4),
                        YounzSurface(
                          tone: _file == null
                              ? YounzSurfaceTone.soft
                              : YounzSurfaceTone.lime,
                          onTap: _pickFile,
                          padding: const EdgeInsets.all(16),
                          child: Row(
                            children: [
                              YounzIconBadge(
                                icon: _file == null
                                    ? Icons.upload_file_rounded
                                    : Icons.check_rounded,
                                background: YounzColors.ink,
                              ),
                              const SizedBox(width: 13),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      _fileName ?? 'Tambahkan file',
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: Theme.of(
                                        context,
                                      ).textTheme.titleSmall,
                                    ),
                                    const SizedBox(height: 3),
                                    const Text(
                                      'PDF, Office, gambar, atau teks. Maksimal 20 MB.',
                                    ),
                                  ],
                                ),
                              ),
                              if (_file != null)
                                IconButton(
                                  tooltip: 'Hapus file',
                                  onPressed: () => setState(() {
                                    _file = null;
                                    _fileName = null;
                                  }),
                                  icon: const Icon(Icons.close_rounded),
                                )
                              else
                                const Icon(Icons.arrow_outward_rounded),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  const YounzNotice(
                    title: 'File tetap privat',
                    message:
                        'Harga final dan pekerjaan selalu dikonfirmasi sebelum diproses.',
                    icon: Icons.lock_outline_rounded,
                    tone: YounzSurfaceTone.ink,
                  ),
                ],
              ),
            ),
          );
        },
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: YounzColors.line)),
          ),
          child: ElevatedButton.icon(
            onPressed: _submitting ? null : _submit,
            icon: _submitting
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.send_rounded),
            label: Text(
              _submitting ? 'Mengirim pesanan...' : 'Kirim untuk diperiksa',
            ),
          ),
        ),
      ),
    );
  }
}

class _FormProgress extends StatelessWidget {
  const _FormProgress();

  @override
  Widget build(BuildContext context) {
    const labels = ['Kontak', 'Layanan', 'Detail'];
    return Row(
      children: labels.indexed.map((entry) {
        return Expanded(
          child: Container(
            margin:
                EdgeInsets.only(right: entry.$1 == labels.length - 1 ? 0 : 8),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
            decoration: BoxDecoration(
              color: entry.$1 == 0 ? YounzColors.lime : Colors.white,
              borderRadius: BorderRadius.circular(YounzRadii.sm),
              border: Border.all(color: YounzColors.line),
            ),
            child: Text(
              '${entry.$1 + 1}. ${entry.$2}',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: YounzColors.ink,
                    letterSpacing: 0,
                  ),
            ),
          ),
        );
      }).toList(),
    );
  }
}

class _OrderSection extends StatelessWidget {
  const _OrderSection({
    required this.number,
    required this.title,
    required this.description,
    required this.child,
  });

  final String number;
  final String title;
  final String description;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 38,
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: YounzColors.blue,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  number,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(description),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          child,
        ],
      ),
    );
  }
}

class _OrderSuccess extends StatelessWidget {
  const _OrderSuccess({required this.result});

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
            icon: Icons.check_rounded,
            background: YounzColors.lime,
            foreground: YounzColors.ink,
            size: 56,
          ),
          const SizedBox(height: 18),
          Text(
            'Brief berhasil masuk.',
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
                const Eyebrow('Nomor pesanan', light: true),
                const SizedBox(height: 10),
                SelectableText(
                  orderNumber,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        color: Colors.white,
                      ),
                ),
                if (result.trackingToken != null) ...[
                  const SizedBox(height: 8),
                  const Text(
                    'Simpan nomor ini bersama nomor WhatsApp untuk pelacakan.',
                    style: TextStyle(color: Colors.white60),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          if (result.redirectUrl?.isNotEmpty == true) ...[
            AccentButton(
              label: 'Lanjut ke pembayaran',
              icon: Icons.open_in_new_rounded,
              expand: true,
              onPressed: () async {
                final target = Uri.tryParse(result.redirectUrl!);
                if (target != null) {
                  await launchUrl(target, mode: LaunchMode.externalApplication);
                }
              },
            ),
            const SizedBox(height: 8),
          ],
          OutlinedButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Selesai'),
          ),
        ],
      ),
    );
  }
}
