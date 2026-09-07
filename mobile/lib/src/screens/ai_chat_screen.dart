import 'package:flutter/material.dart';

import '../api_client.dart';
import '../theme.dart';
import '../widgets.dart';

class AiChatScreen extends StatefulWidget {
  const AiChatScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<AiChatScreen> createState() => _AiChatScreenState();
}

class _AiChatScreenState extends State<AiChatScreen> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  final List<_ChatMessage> _messages = [];
  bool _consent = false;
  bool _pending = false;

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _send([String? suggestion]) async {
    final question = (suggestion ?? _input.text).trim();
    if (question.isEmpty || !_consent || _pending) return;
    final history = _messages
        .map(
          (item) => <String, dynamic>{
            'role': item.user ? 'user' : 'assistant',
            'content': item.text,
          },
        )
        .take(12)
        .toList(growable: false);
    setState(() {
      _messages.add(_ChatMessage(text: question, user: true));
      _input.clear();
      _pending = true;
    });
    _scrollToEnd();
    try {
      final reply = await widget.api.chat(message: question, history: history);
      if (!mounted) return;
      setState(
        () => _messages.add(
          _ChatMessage(text: reply.message, sources: reply.sources),
        ),
      );
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(
        () => _messages.add(_ChatMessage(text: error.message, failed: true)),
      );
    } finally {
      if (mounted) setState(() => _pending = false);
      _scrollToEnd();
    }
  }

  void _selectSuggestion(String value) {
    if (_consent) {
      _send(value);
      return;
    }
    setState(() => _input.text = value);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Aktifkan persetujuan AI sebelum mengirim pertanyaan.'),
      ),
    );
  }

  void _scrollToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scroll.hasClients) return;
      _scroll.animateTo(
        _scroll.position.maxScrollExtent,
        duration: const Duration(milliseconds: 320),
        curve: Curves.easeOutCubic,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: YounzColors.paper,
      appBar: AppBar(
        backgroundColor: YounzColors.paper,
        foregroundColor: YounzColors.ink,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          onPressed: () => Navigator.of(context).pop(),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        titleSpacing: 0,
        title: Row(
          children: [
            const Icon(Icons.smart_toy_outlined, color: YounzColors.primary),
            SizedBox(width: 11),
            const Text(
              'Younz AI',
              style: TextStyle(
                color: YounzColors.primary,
                fontSize: 20,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            onPressed:
                _messages.isEmpty ? null : () => setState(_messages.clear),
            tooltip: 'Hapus percakapan',
            icon: const Icon(Icons.refresh_rounded),
          ),
          const SizedBox(width: 6),
        ],
      ),
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [YounzColors.paper, YounzColors.paper],
          ),
        ),
        child: Column(
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
              color: YounzColors.blueWash,
              child: const Row(
                children: [
                  Icon(Icons.info_outline_rounded,
                      size: 16, color: YounzColors.primary),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Hindari data pribadi, password, dan informasi rahasia.',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: YounzColors.primary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 260),
                child: _messages.isEmpty
                    ? _EmptyAi(
                        key: const ValueKey('empty-ai'),
                        onSuggestion: _selectSuggestion,
                      )
                    : ListView.builder(
                        key: const ValueKey('message-list'),
                        controller: _scroll,
                        padding: const EdgeInsets.fromLTRB(14, 18, 14, 18),
                        itemCount: _messages.length + (_pending ? 1 : 0),
                        itemBuilder: (context, index) {
                          if (index == _messages.length) {
                            return const _ThinkingBubble();
                          }
                          return _MessageBubble(message: _messages[index]);
                        },
                      ),
              ),
            ),
            _Composer(
              controller: _input,
              consent: _consent,
              pending: _pending,
              onConsentChanged: (value) => setState(() => _consent = value),
              onSend: _send,
            ),
          ],
        ),
      ),
    );
  }
}

class _Composer extends StatelessWidget {
  const _Composer({
    required this.controller,
    required this.consent,
    required this.pending,
    required this.onConsentChanged,
    required this.onSend,
  });

  final TextEditingController controller;
  final bool consent;
  final bool pending;
  final ValueChanged<bool> onConsentChanged;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    final textScale = MediaQuery.textScalerOf(context).scale(14) / 14;
    final largeText = textScale > 1.4;
    final field = TextField(
      controller: controller,
      enabled: !pending,
      minLines: 1,
      maxLines: 4,
      textCapitalization: TextCapitalization.sentences,
      textInputAction: TextInputAction.send,
      onSubmitted: (_) => onSend(),
      decoration: const InputDecoration(
        hintText: 'Tanya Younz AI sesuatu...',
        prefixIcon: Icon(Icons.add_circle_outline_rounded),
      ),
    );
    final sendIcon = pending
        ? const SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(strokeWidth: 2),
          )
        : const Icon(Icons.send_rounded);
    return SafeArea(
      top: false,
      minimum: const EdgeInsets.fromLTRB(0, 0, 0, 0),
      child: Container(
        padding: const EdgeInsets.fromLTRB(18, 10, 18, 12),
        decoration: BoxDecoration(
          color: Colors.white,
          border: const Border(top: BorderSide(color: YounzColors.line)),
        ),
        child: Column(
          children: [
            if (largeText) ...[
              Row(
                children: [
                  Semantics(
                    label: 'Persetujuan pemrosesan AI',
                    child: Switch.adaptive(
                      value: consent,
                      activeTrackColor: YounzColors.blue,
                      onChanged: pending ? null : onConsentChanged,
                    ),
                  ),
                  const SizedBox(width: 4),
                  const Expanded(
                    child: Text(
                      'Saya setuju pertanyaan diproses AI.',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              Align(
                alignment: Alignment.centerLeft,
                child: StatusPill(
                  consent ? 'AKTIF' : 'NONAKTIF',
                  good: consent,
                ),
              ),
            ] else
              Row(
                children: [
                  Semantics(
                    label: 'Persetujuan pemrosesan AI',
                    child: Switch.adaptive(
                      value: consent,
                      activeTrackColor: YounzColors.blue,
                      onChanged: pending ? null : onConsentChanged,
                    ),
                  ),
                  const SizedBox(width: 4),
                  const Expanded(
                    child: Text(
                      'Saya setuju pertanyaan diproses AI.',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  StatusPill(consent ? 'AKTIF' : 'NONAKTIF', good: consent),
                ],
              ),
            const SizedBox(height: 8),
            if (largeText) ...[
              field,
              const SizedBox(height: 8),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: consent && !pending ? onSend : null,
                  style: FilledButton.styleFrom(
                    backgroundColor: YounzColors.lime,
                    foregroundColor: YounzColors.ink,
                    disabledBackgroundColor: YounzColors.line,
                  ),
                  icon: sendIcon,
                  label: const Text('Kirim pertanyaan'),
                ),
              ),
            ] else
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(child: field),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: consent && !pending ? onSend : null,
                    style: IconButton.styleFrom(
                      minimumSize: const Size(52, 52),
                      backgroundColor: YounzColors.brandLime,
                      foregroundColor: YounzColors.ink,
                      disabledBackgroundColor: YounzColors.line,
                    ),
                    icon: sendIcon,
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

class _ChatMessage {
  const _ChatMessage({
    required this.text,
    this.user = false,
    this.sources = const [],
    this.failed = false,
  });

  final String text;
  final bool user;
  final List<String> sources;
  final bool failed;
}

class _EmptyAi extends StatelessWidget {
  const _EmptyAi({super.key, required this.onSuggestion});

  final ValueChanged<String> onSuggestion;

  @override
  Widget build(BuildContext context) {
    const suggestions = [
      (
        'Harga awal',
        'Berapa harga awal print A4?',
        Icons.payments_outlined,
      ),
      (
        'Layanan web',
        'Apakah Younz bisa membuat website?',
        Icons.language_rounded,
      ),
      (
        'Jam operasional',
        'Younz buka jam berapa?',
        Icons.schedule_rounded,
      ),
    ];
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(18, 38, 18, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: 96,
            height: 96,
            decoration: const BoxDecoration(
              color: YounzColors.blue,
              shape: BoxShape.circle,
              boxShadow: YounzElevation.surface,
            ),
            child: const Icon(
              Icons.smart_toy_outlined,
              color: Colors.white,
              size: 48,
            ),
          ),
          const SizedBox(height: 24),
          Text(
            'How can I help you today?',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  fontSize: 28,
                ),
          ),
          const SizedBox(height: 8),
          Text(
            "I'm Younz AI, your digital assistant.",
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyLarge,
          ),
          const SizedBox(height: 24),
          Wrap(
            alignment: WrapAlignment.center,
            spacing: 8,
            runSpacing: 8,
            children: suggestions.map((suggestion) {
              return ActionChip(
                onPressed: () => onSuggestion(suggestion.$2),
                avatar: Icon(suggestion.$3, size: 17),
                label: Text(suggestion.$1),
                backgroundColor: YounzColors.paper,
                side: const BorderSide(color: YounzColors.controlLine),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(999),
                ),
              );
            }).toList(growable: false),
          ),
          const SizedBox(height: 34),
          Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'Mulai percakapan baru',
              style: Theme.of(context).textTheme.labelMedium,
            ),
          ),
        ],
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({required this.message});

  final _ChatMessage message;

  @override
  Widget build(BuildContext context) {
    final maxWidth = MediaQuery.sizeOf(context).width * .84;
    return Align(
      alignment: message.user ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(maxWidth: maxWidth),
        margin: const EdgeInsets.only(bottom: 14),
        child: Column(
          crossAxisAlignment:
              message.user ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            if (!message.user)
              const Padding(
                padding: EdgeInsets.only(left: 3, bottom: 6),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    YounzMark(size: 25),
                    SizedBox(width: 7),
                    Text(
                      'Younz AI',
                      style:
                          TextStyle(fontSize: 11, fontWeight: FontWeight.w900),
                    ),
                  ],
                ),
              ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
              decoration: BoxDecoration(
                color: message.failed
                    ? YounzColors.dangerWash
                    : message.user
                        ? YounzColors.blue
                        : Colors.white,
                borderRadius: BorderRadius.circular(20).copyWith(
                  bottomRight: message.user ? const Radius.circular(5) : null,
                  bottomLeft: message.user ? null : const Radius.circular(5),
                ),
                border: message.user
                    ? null
                    : Border.all(
                        color: message.failed
                            ? const Color(0xFFF2C4C0)
                            : YounzColors.line,
                      ),
                boxShadow: message.user ? null : YounzElevation.surface,
              ),
              child: Text(
                message.text,
                style: TextStyle(
                  color: message.user ? Colors.white : YounzColors.ink,
                  height: 1.5,
                ),
              ),
            ),
            if (message.sources.isNotEmpty) ...[
              const SizedBox(height: 7),
              Wrap(
                spacing: 5,
                runSpacing: 5,
                children: message.sources
                    .map((source) => StatusPill(source))
                    .toList(growable: false),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ThinkingBubble extends StatelessWidget {
  const _ThinkingBubble();

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: YounzColors.line),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            YounzMark(size: 28),
            SizedBox(width: 9),
            SizedBox(
              width: 16,
              height: 16,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
            SizedBox(width: 9),
            Text('Menyusun jawaban...'),
          ],
        ),
      ),
    );
  }
}
