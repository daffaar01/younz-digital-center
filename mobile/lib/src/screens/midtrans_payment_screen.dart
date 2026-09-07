import 'dart:async';

import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../api_client.dart';
import '../models.dart';
import '../theme.dart';
import '../widgets.dart';

typedef PaymentStatusLoader = Future<DigitalProductOrderStatus> Function();

class MidtransPaymentScreen extends StatefulWidget {
  const MidtransPaymentScreen({
    super.key,
    required this.paymentUrl,
    required this.orderNumber,
    this.completionHost,
    this.statusLoader,
    this.statusRefresher,
  });

  final String paymentUrl;
  final String orderNumber;
  final String? completionHost;
  final PaymentStatusLoader? statusLoader;
  final PaymentStatusLoader? statusRefresher;

  @override
  State<MidtransPaymentScreen> createState() => _MidtransPaymentScreenState();
}

class _MidtransPaymentScreenState extends State<MidtransPaymentScreen>
    with WidgetsBindingObserver {
  late final WebViewController _controller;
  Timer? _statusTimer;
  DigitalProductOrderStatus? _status;
  int _progress = 0;
  bool _checking = false;
  bool _verifyingReturn = false;
  String? _mainFrameError;
  String? _statusError;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white)
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (progress) {
            if (mounted) setState(() => _progress = progress);
          },
          onPageStarted: (_) {
            if (mounted) {
              setState(() {
                _mainFrameError = null;
                _progress = 0;
              });
            }
          },
          onPageFinished: (_) {
            if (mounted) setState(() => _progress = 100);
          },
          onWebResourceError: (error) {
            if (error.isForMainFrame == true && mounted) {
              setState(() => _mainFrameError = error.description);
            }
          },
          onNavigationRequest: _handleNavigation,
          onUrlChange: (change) {
            final value = change.url;
            if (value != null) _handlePossibleCompletion(value);
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.paymentUrl));

    if (widget.statusLoader != null) {
      _statusTimer = Timer.periodic(
        const Duration(seconds: 5),
        (_) => unawaited(_checkStatus(silent: true)),
      );
      unawaited(_checkStatus(silent: true));
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _statusTimer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && widget.statusRefresher != null) {
      unawaited(_checkStatus(reconcile: true, silent: true));
    }
  }

  NavigationDecision _handleNavigation(NavigationRequest request) {
    final target = Uri.tryParse(request.url);
    if (target == null) return NavigationDecision.prevent;
    if (_isCompletionUrl(target)) {
      unawaited(_checkStatus(reconcile: true));
      return NavigationDecision.prevent;
    }
    if (target.scheme == 'https' ||
        target.scheme == 'http' ||
        target.scheme == 'about' ||
        target.scheme == 'data' ||
        target.scheme == 'blob') {
      return NavigationDecision.navigate;
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Tautan aplikasi pembayaran tidak dibuka di browser. Pilih QRIS, kartu, atau transfer bank di halaman Midtrans.',
          ),
        ),
      );
    });
    return NavigationDecision.prevent;
  }

  void _handlePossibleCompletion(String value) {
    final target = Uri.tryParse(value);
    if (target != null && _isCompletionUrl(target)) {
      unawaited(_checkStatus(reconcile: true));
    }
  }

  bool _isCompletionUrl(Uri target) {
    final host = widget.completionHost;
    return host != null &&
        host.isNotEmpty &&
        target.host.toLowerCase() == host.toLowerCase() &&
        target.path.startsWith('/cek-pesanan/');
  }

  Future<void> _checkStatus({
    bool reconcile = false,
    bool silent = false,
  }) async {
    if (_checking) return;
    final loader = reconcile ? widget.statusRefresher : widget.statusLoader;
    if (loader == null) return;
    setState(() {
      _checking = true;
      _verifyingReturn = reconcile;
      if (!silent) _statusError = null;
    });
    try {
      final next = await loader();
      if (!mounted) return;
      setState(() {
        _status = next;
        _statusError = null;
      });
      if (next.paymentTerminal) _statusTimer?.cancel();
    } on ApiException catch (error) {
      if (!mounted || silent) return;
      setState(() => _statusError = error.message);
    } finally {
      if (mounted) {
        setState(() {
          _checking = false;
          _verifyingReturn = false;
        });
      }
    }
  }

  Future<void> _handleBack() async {
    if (_status?.paymentTerminal == true) {
      if (mounted) Navigator.of(context).pop(_status);
      return;
    }
    if (await _controller.canGoBack()) {
      await _controller.goBack();
      return;
    }
    if (mounted) Navigator.of(context).pop(_status);
  }

  @override
  Widget build(BuildContext context) {
    final status = _status;
    if (status?.paymentTerminal == true) {
      return _PaymentOutcome(
        status: status!,
        onClose: () => Navigator.of(context).pop(status),
      );
    }

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) unawaited(_handleBack());
      },
      child: Scaffold(
        backgroundColor: Colors.white,
        appBar: AppBar(
          leading: IconButton(
            tooltip: 'Kembali',
            onPressed: _handleBack,
            icon: const Icon(Icons.arrow_back_rounded),
          ),
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Pembayaran Midtrans'),
              Text(
                widget.orderNumber,
                style: Theme.of(context).textTheme.labelSmall,
              ),
            ],
          ),
          actions: [
            if (widget.statusRefresher != null)
              IconButton(
                tooltip: 'Periksa status pembayaran',
                onPressed: _checking
                    ? null
                    : () => _checkStatus(reconcile: true),
                icon: const Icon(Icons.refresh_rounded),
              ),
          ],
          bottom: _progress < 100
              ? PreferredSize(
                  preferredSize: const Size.fromHeight(3),
                  child: LinearProgressIndicator(
                    value: _progress == 0 ? null : _progress / 100,
                    minHeight: 3,
                    color: YounzColors.primary,
                    backgroundColor: YounzColors.blueWash,
                  ),
                )
              : null,
        ),
        body: Stack(
          children: [
            if (_mainFrameError == null)
              WebViewWidget(controller: _controller)
            else
              ErrorState(
                message:
                    'Halaman pembayaran belum dapat dimuat. $_mainFrameError',
                onRetry: () {
                  setState(() => _mainFrameError = null);
                  _controller.reload();
                },
              ),
            if (_verifyingReturn)
              const ColoredBox(
                color: Color(0xCCFFFFFF),
                child: Center(
                  child: LoadingState(
                    label: 'Memverifikasi pembayaran ke Midtrans...',
                  ),
                ),
              ),
          ],
        ),
        bottomNavigationBar: widget.statusLoader == null
            ? null
            : SafeArea(
                top: false,
                child: Container(
                  padding: const EdgeInsets.fromLTRB(16, 10, 10, 10),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    border: Border(
                      top: BorderSide(color: YounzColors.controlLine),
                    ),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.lock_outline_rounded,
                        color: YounzColors.primary,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          _statusError ??
                              (status == null
                                  ? 'Menunggu pembayaran'
                                  : _paymentStatusLabel(
                                      status.paymentStatus,
                                    )),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.labelMedium,
                        ),
                      ),
                      TextButton(
                        onPressed: _checking
                            ? null
                            : () => _checkStatus(reconcile: true),
                        child: Text(_checking ? 'Memeriksa...' : 'Cek status'),
                      ),
                    ],
                  ),
                ),
              ),
      ),
    );
  }
}

class _PaymentOutcome extends StatelessWidget {
  const _PaymentOutcome({required this.status, required this.onClose});

  final DigitalProductOrderStatus status;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final success = status.isPaid;
    return Scaffold(
      backgroundColor: YounzColors.paper,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              YounzIconBadge(
                icon: success
                    ? Icons.check_circle_outline_rounded
                    : Icons.error_outline_rounded,
                background:
                    success ? YounzColors.lime : const Color(0xFFFFD7D2),
                foreground:
                    success ? YounzColors.ink : YounzColors.danger,
                size: 72,
              ),
              const SizedBox(height: 24),
              Text(
                success
                    ? 'Pembayaran berhasil.'
                    : 'Pembayaran belum berhasil.',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineLarge,
              ),
              const SizedBox(height: 10),
              Text(
                success
                    ? 'Pesanan ${status.orderNumber} sudah masuk dan tim Younz menerima notifikasi untuk menyiapkannya.'
                    : '${_paymentStatusLabel(status.paymentStatus)} untuk pesanan ${status.orderNumber}.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              YounzSurface(
                tone: YounzSurfaceTone.ink,
                child: Column(
                  children: [
                    Text(
                      status.productName,
                      style: Theme.of(context)
                          .textTheme
                          .titleLarge
                          ?.copyWith(color: Colors.white),
                    ),
                    if (status.variantLabel?.isNotEmpty == true) ...[
                      const SizedBox(height: 4),
                      Text(
                        status.variantLabel!,
                        style: const TextStyle(color: Colors.white70),
                      ),
                    ],
                    const SizedBox(height: 8),
                    Text(
                      formatRupiah(status.amount),
                      style: Theme.of(context)
                          .textTheme
                          .headlineSmall
                          ?.copyWith(color: YounzColors.lime),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              ElevatedButton.icon(
                onPressed: onClose,
                icon: const Icon(Icons.arrow_back_rounded),
                label: const Text('Kembali ke produk'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

String _paymentStatusLabel(String status) {
  return switch (status) {
    'paid' => 'Pembayaran terverifikasi',
    'expired' => 'Waktu pembayaran habis',
    'cancelled' => 'Pembayaran dibatalkan',
    'failed' => 'Pembayaran ditolak',
    'refunded' => 'Pembayaran dikembalikan',
    _ => 'Menunggu pembayaran',
  };
}
