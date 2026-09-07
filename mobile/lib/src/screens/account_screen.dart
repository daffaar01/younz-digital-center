import 'package:flutter/material.dart';

import '../api_client.dart';
import '../models.dart';
import '../theme.dart';
import '../widgets.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({
    super.key,
    required this.api,
    this.onAuthenticated,
    this.onLoggedOut,
    this.showNavigation = true,
  });

  final ApiClient api;
  final VoidCallback? onAuthenticated;
  final VoidCallback? onLoggedOut;
  final bool showNavigation;

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  bool _checking = true;
  bool _loggedIn = false;
  bool _registerMode = false;
  Future<PortalData>? _portalFuture;

  @override
  void initState() {
    super.initState();
    _checkSession();
  }

  Future<void> _checkSession() async {
    final active = await widget.api.hasSession;
    if (!mounted) return;
    setState(() {
      _loggedIn = active;
      _checking = false;
      if (active) _portalFuture = widget.api.fetchPortal();
    });
  }

  void _signedIn() {
    final onAuthenticated = widget.onAuthenticated;
    if (onAuthenticated != null) {
      onAuthenticated();
      return;
    }
    setState(() {
      _loggedIn = true;
      _portalFuture = widget.api.fetchPortal();
    });
  }

  Future<void> _logout() async {
    await widget.api.logout();
    if (!mounted) return;
    final onLoggedOut = widget.onLoggedOut;
    if (onLoggedOut != null) {
      onLoggedOut();
      return;
    }
    setState(() {
      _loggedIn = false;
      _portalFuture = null;
    });
  }

  void _reloadPortal() {
    setState(() => _portalFuture = widget.api.fetchPortal());
  }

  @override
  Widget build(BuildContext context) {
    if (_checking) return const LoadingState(label: 'Menyiapkan akun...');
    if (!_loggedIn) {
      return _AuthView(
        api: widget.api,
        registerMode: _registerMode,
        onModeChanged: (value) => setState(() => _registerMode = value),
        onSignedIn: _signedIn,
        showNavigation: widget.showNavigation,
      );
    }
    return _PortalView(
      api: widget.api,
      future: _portalFuture!,
      onLogout: _logout,
      onRefresh: _reloadPortal,
      showNavigation: widget.showNavigation,
    );
  }
}

class _AuthView extends StatelessWidget {
  const _AuthView({
    required this.api,
    required this.registerMode,
    required this.onModeChanged,
    required this.onSignedIn,
    required this.showNavigation,
  });

  final ApiClient api;
  final bool registerMode;
  final ValueChanged<bool> onModeChanged;
  final VoidCallback onSignedIn;
  final bool showNavigation;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: YounzColors.paper,
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: YounzTopBar(
              title: 'Akun saya',
              showNavigation: showNavigation,
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 32, 20, 0),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Akun Saya',
                    style: Theme.of(context).textTheme.headlineLarge,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Masuk untuk melihat progres, riwayat, dan ringkasan pesanan Anda.',
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 22, 20, 132),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _AuthModeSwitch(
                    registerMode: registerMode,
                    onChanged: onModeChanged,
                  ),
                  const SizedBox(height: 14),
                  AnimatedSwitcher(
                    duration: const Duration(milliseconds: 260),
                    transitionBuilder: (child, animation) => FadeTransition(
                      opacity: animation,
                      child: SizeTransition(
                        sizeFactor: animation,
                        axisAlignment: -1,
                        child: child,
                      ),
                    ),
                    child: registerMode
                        ? _RegisterForm(
                            key: const ValueKey('register'),
                            api: api,
                            onDone: () => onModeChanged(false),
                          )
                        : _LoginForm(
                            key: const ValueKey('login'),
                            api: api,
                            onSignedIn: onSignedIn,
                          ),
                  ),
                  const SizedBox(height: 14),
                  const YounzNotice(
                    title: 'Akun aman, proses transparan',
                    message:
                        'Token sesi disimpan pada perangkat dan hanya digunakan untuk mengakses backend Younz.',
                    icon: Icons.shield_outlined,
                    tone: YounzSurfaceTone.lime,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AuthModeSwitch extends StatelessWidget {
  const _AuthModeSwitch({required this.registerMode, required this.onChanged});

  final bool registerMode;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(6),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(YounzRadii.md),
        border: Border.all(color: YounzColors.line),
      ),
      child: Row(
        children: [
          _AuthModeButton(
            label: 'Masuk',
            selected: !registerMode,
            onTap: () => onChanged(false),
          ),
          const SizedBox(width: 6),
          _AuthModeButton(
            label: 'Daftar',
            selected: registerMode,
            onTap: () => onChanged(true),
          ),
        ],
      ),
    );
  }
}

class _AuthModeButton extends StatelessWidget {
  const _AuthModeButton({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
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
          color: selected ? YounzColors.blue : Colors.transparent,
          borderRadius: BorderRadius.circular(13),
          child: InkWell(
            borderRadius: BorderRadius.circular(13),
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 13),
              child: Text(
                label,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: selected ? Colors.white : YounzColors.muted,
                    ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _LoginForm extends StatefulWidget {
  const _LoginForm({super.key, required this.api, required this.onSignedIn});

  final ApiClient api;
  final VoidCallback onSignedIn;

  @override
  State<_LoginForm> createState() => _LoginFormState();
}

class _LoginFormState extends State<_LoginForm> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _pending = false;
  bool _obscure = true;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _pending) return;
    setState(() {
      _pending = true;
      _error = null;
    });
    try {
      await widget.api.login(email: _email.text, password: _password.text);
      widget.onSignedIn();
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _pending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      shadow: true,
      padding: const EdgeInsets.all(19),
      child: Form(
        key: _formKey,
        child: AutofillGroup(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Eyebrow('Selamat datang kembali'),
              const SizedBox(height: 10),
              Text(
                'Masuk ke portal Anda.',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 7),
              const Text('Email harus sudah diverifikasi sebelum masuk.'),
              const SizedBox(height: 20),
              TextFormField(
                controller: _email,
                autofillHints: const [
                  AutofillHints.username,
                  AutofillHints.email
                ],
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
                controller: _password,
                autofillHints: const [AutofillHints.password],
                obscureText: _obscure,
                onFieldSubmitted: (_) => _submit(),
                decoration: InputDecoration(
                  labelText: 'Password',
                  prefixIcon: const Icon(Icons.lock_outline_rounded),
                  suffixIcon: IconButton(
                    tooltip: _obscure
                        ? 'Tampilkan password'
                        : 'Sembunyikan password',
                    onPressed: () => setState(() => _obscure = !_obscure),
                    icon: Icon(
                      _obscure
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                    ),
                  ),
                ),
                validator: (value) => value == null || value.isEmpty
                    ? 'Masukkan password.'
                    : null,
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                _AuthError(message: _error!),
              ],
              const SizedBox(height: 18),
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
                    : const Icon(Icons.login_rounded),
                label: Text(_pending ? 'Memeriksa...' : 'Masuk ke portal'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RegisterForm extends StatefulWidget {
  const _RegisterForm({super.key, required this.api, required this.onDone});

  final ApiClient api;
  final VoidCallback onDone;

  @override
  State<_RegisterForm> createState() => _RegisterFormState();
}

class _RegisterFormState extends State<_RegisterForm> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  bool _pending = false;
  bool _resending = false;
  bool _obscure = true;
  bool _canResend = false;
  String? _error;
  String? _registrationMessage;
  String? _registeredEmail;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _pending) return;
    setState(() {
      _pending = true;
      _error = null;
      _canResend = false;
    });
    try {
      final message = await widget.api.register(
        name: _name.text,
        email: _email.text,
        phone: _phone.text,
        password: _password.text,
      );
      if (!mounted) return;
      setState(() {
        _registrationMessage = message;
        _registeredEmail = _email.text.trim().toLowerCase();
      });
    } on ApiException catch (error) {
      final duplicateEmail = error.errors['email']?.any(
            (message) => message.toLowerCase().contains('sudah terdaftar'),
          ) ??
          false;
      if (mounted) {
        setState(() {
          _error = _apiErrorText(error);
          _canResend = duplicateEmail;
        });
      }
    } finally {
      if (mounted) setState(() => _pending = false);
    }
  }

  Future<void> _resendVerification() async {
    if (_resending) return;
    setState(() {
      _resending = true;
      _error = null;
    });
    try {
      final message = await widget.api.resendEmailVerification(
        email: _email.text,
        password: _password.text,
      );
      if (!mounted) return;
      setState(() => _registrationMessage = message);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = _apiErrorText(error));
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final registrationMessage = _registrationMessage;
    if (registrationMessage != null) {
      return _RegistrationSuccess(
        email: _registeredEmail ?? _email.text.trim(),
        message: registrationMessage,
        error: _error,
        resending: _resending,
        onResend: _resendVerification,
        onContinue: widget.onDone,
      );
    }

    final password = _password.text;
    final requirements = [
      ('12+ karakter', password.length >= 12),
      (
        'Huruf besar dan kecil',
        RegExp(r'[A-Z]').hasMatch(password) &&
            RegExp(r'[a-z]').hasMatch(password)
      ),
      ('Minimal satu angka', RegExp(r'\d').hasMatch(password)),
    ];
    return YounzSurface(
      shadow: true,
      padding: const EdgeInsets.all(19),
      child: Form(
        key: _formKey,
        child: AutofillGroup(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Eyebrow('Mulai lebih teratur'),
              const SizedBox(height: 10),
              Text(
                'Buat akun pelanggan.',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 7),
              const Text('Tautan verifikasi akan dikirim ke email Anda.'),
              const SizedBox(height: 20),
              TextFormField(
                controller: _name,
                autofillHints: const [AutofillHints.name],
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
                autofillHints: const [AutofillHints.email],
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
                autofillHints: const [AutofillHints.telephoneNumber],
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(
                  labelText: 'Nomor WhatsApp',
                  prefixIcon: Icon(Icons.chat_outlined),
                ),
                validator: (value) =>
                    (value?.replaceAll(RegExp(r'\D'), '').length ?? 0) < 9
                        ? 'Periksa nomor WhatsApp.'
                        : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _password,
                autofillHints: const [AutofillHints.newPassword],
                obscureText: _obscure,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  labelText: 'Password',
                  prefixIcon: const Icon(Icons.lock_outline_rounded),
                  suffixIcon: IconButton(
                    tooltip: _obscure
                        ? 'Tampilkan password'
                        : 'Sembunyikan password',
                    onPressed: () => setState(() => _obscure = !_obscure),
                    icon: Icon(
                      _obscure
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                    ),
                  ),
                ),
                validator: (value) {
                  final candidate = value ?? '';
                  if (candidate.length < 12 ||
                      !RegExp(r'[A-Z]').hasMatch(candidate) ||
                      !RegExp(r'[a-z]').hasMatch(candidate) ||
                      !RegExp(r'\d').hasMatch(candidate)) {
                    return 'Password belum memenuhi ketentuan.';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: requirements
                    .map(
                      (item) => _RequirementPill(
                        label: item.$1,
                        valid: item.$2,
                      ),
                    )
                    .toList(),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                _AuthError(message: _error!),
              ],
              if (_canResend) ...[
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: _resending ? null : _resendVerification,
                  icon: const Icon(Icons.mark_email_unread_outlined),
                  label: const Text('Kirim ulang email akun ini'),
                ),
              ],
              const SizedBox(height: 18),
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
                    : const Icon(Icons.person_add_alt_1_rounded),
                label: Text(
                  _pending ? 'Membuat akun...' : 'Daftar dan verifikasi email',
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RegistrationSuccess extends StatelessWidget {
  const _RegistrationSuccess({
    required this.email,
    required this.message,
    required this.error,
    required this.resending,
    required this.onResend,
    required this.onContinue,
  });

  final String email;
  final String message;
  final String? error;
  final bool resending;
  final VoidCallback onResend;
  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      shadow: true,
      padding: const EdgeInsets.all(19),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const YounzIconBadge(
            icon: Icons.mark_email_read_outlined,
            background: YounzColors.lime,
            foreground: YounzColors.ink,
            size: 54,
          ),
          const SizedBox(height: 18),
          Text(
            'Akun berhasil dibuat.',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 8),
          Text(message),
          const SizedBox(height: 16),
          YounzSurface(
            tone: YounzSurfaceTone.lime,
            padding: const EdgeInsets.all(13),
            child: Row(
              children: [
                const Icon(Icons.alternate_email_rounded),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    email,
                    style: Theme.of(context).textTheme.labelLarge,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'Buka inbox atau folder spam, tekan tautan verifikasi, lalu kembali ke aplikasi untuk masuk.',
          ),
          if (error != null) ...[
            const SizedBox(height: 14),
            _AuthError(message: error!),
          ],
          const SizedBox(height: 20),
          ElevatedButton.icon(
            onPressed: onContinue,
            icon: const Icon(Icons.login_rounded),
            label: const Text('Saya sudah verifikasi, masuk'),
          ),
          const SizedBox(height: 9),
          OutlinedButton.icon(
            onPressed: resending ? null : onResend,
            icon: resending
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh_rounded),
            label: Text(
              resending ? 'Mengirim ulang...' : 'Kirim ulang email verifikasi',
            ),
          ),
        ],
      ),
    );
  }
}

String _apiErrorText(ApiException error) {
  final messages = error.errors.values
      .expand((values) => values)
      .where((message) => message.trim().isNotEmpty)
      .toSet()
      .toList(growable: false);
  return messages.isEmpty ? error.message : messages.join('\n');
}

class _AuthError extends StatelessWidget {
  const _AuthError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      tone: YounzSurfaceTone.white,
      padding: const EdgeInsets.all(12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline_rounded, color: YounzColors.danger),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                color: YounzColors.danger,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _RequirementPill extends StatelessWidget {
  const _RequirementPill({required this.label, required this.valid});

  final String label;
  final bool valid;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
      decoration: BoxDecoration(
        color: valid ? YounzColors.greenWash : YounzColors.paper,
        borderRadius: BorderRadius.circular(YounzRadii.sm),
        border: Border.all(color: valid ? YounzColors.green : YounzColors.line),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            valid ? Icons.check_rounded : Icons.circle_outlined,
            size: 15,
            color: valid ? YounzColors.green : YounzColors.muted,
          ),
          const SizedBox(width: 5),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: valid ? YounzColors.green : YounzColors.muted,
                  letterSpacing: 0,
                ),
          ),
        ],
      ),
    );
  }
}

class _PortalView extends StatelessWidget {
  const _PortalView({
    required this.api,
    required this.future,
    required this.onLogout,
    required this.onRefresh,
    required this.showNavigation,
  });

  final ApiClient api;
  final Future<PortalData> future;
  final Future<void> Function() onLogout;
  final VoidCallback onRefresh;
  final bool showNavigation;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: YounzColors.paper,
      body: FutureBuilder<PortalData>(
        future: future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const LoadingState(label: 'Membuka portal pelanggan...');
          }
          if (snapshot.hasError || snapshot.data == null) {
            return ErrorState(
              message: snapshot.error.toString(),
              onRetry: onRefresh,
            );
          }
          final portal = snapshot.data!;
          return RefreshIndicator(
            onRefresh: () async => onRefresh(),
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverToBoxAdapter(
                  child: YounzTopBar(
                    title: 'Akun saya',
                    showNavigation: showNavigation,
                    actions: [
                      IconButton(
                        tooltip: 'Keluar',
                        onPressed: () => onLogout(),
                        icon: const Icon(Icons.logout_rounded),
                      ),
                    ],
                  ),
                ),
                SliverToBoxAdapter(child: _ProfileHero(portal: portal)),
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 132),
                  sliver: SliverToBoxAdapter(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _SummaryGrid(portal: portal),
                        const SizedBox(height: 16),
                        _HistorySection(orders: portal.orders),
                        const SizedBox(height: 16),
                        _SettingsList(portal: portal),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _ProfileHero extends StatelessWidget {
  const _ProfileHero({required this.portal});

  final PortalData portal;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 20, 20, 0),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: YounzColors.line),
        boxShadow: YounzElevation.surface,
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          Container(
            width: double.infinity,
            height: 96,
            color: YounzColors.blue,
          ),
          Transform.translate(
            offset: const Offset(0, -42),
            child: Column(
              children: [
                Stack(
                  alignment: Alignment.bottomRight,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(5),
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                      ),
                      child: const CircleAvatar(
                        radius: 42,
                        backgroundColor: YounzColors.blueWash,
                        child: Icon(
                          Icons.person_rounded,
                          size: 48,
                          color: YounzColors.primary,
                        ),
                      ),
                    ),
                    Tooltip(
                      message: 'Lihat informasi profil',
                      child: Material(
                        color: YounzColors.brandLime,
                        shape: const CircleBorder(),
                        child: InkWell(
                          customBorder: const CircleBorder(),
                          onTap: () => _showProfileInformation(context, portal),
                          child: const SizedBox(
                            width: 32,
                            height: 32,
                            child: Icon(Icons.edit_rounded, size: 16),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  portal.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 3),
                Text(portal.email),
                const SizedBox(height: 8),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: YounzColors.ink,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: const Text(
                    'Premium Member',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.portal});

  final PortalData portal;

  @override
  Widget build(BuildContext context) {
    final largeText = MediaQuery.textScalerOf(context).scale(14) / 14 > 1.4;
    if (largeText) {
      return Column(
        children: [
          _AccountStat(
            label: 'TOTAL ORDERS',
            value: '${portal.totalOrders}',
            background: const Color(0xFFE7E5E5),
          ),
          const SizedBox(height: 10),
          _AccountStat(
            label: 'Active',
            value: '${portal.activeOrders}',
            background: YounzColors.blue,
            foreground: Colors.white,
            icon: Icons.sync_rounded,
          ),
          const SizedBox(height: 10),
          _AccountStat(
            label: 'Completed',
            value: '${portal.completedOrders}',
            icon: Icons.check_circle_outline_rounded,
          ),
        ],
      );
    }
    return SizedBox(
      // Give the compact cards enough vertical room for their label and value
      // at narrow phone widths without clipping the number.
      height: 172,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: _AccountStat(
              label: 'TOTAL ORDERS',
              value: '${portal.totalOrders}',
              background: const Color(0xFFE7E5E5),
              prominent: true,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              children: [
                Expanded(
                  child: _AccountStat(
                    label: 'Active',
                    value: '${portal.activeOrders}',
                    background: YounzColors.blue,
                    foreground: Colors.white,
                    icon: Icons.sync_rounded,
                  ),
                ),
                const SizedBox(height: 10),
                Expanded(
                  child: _AccountStat(
                    label: 'Completed',
                    value: '${portal.completedOrders}',
                    icon: Icons.check_circle_outline_rounded,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AccountStat extends StatelessWidget {
  const _AccountStat({
    required this.label,
    required this.value,
    this.background = Colors.white,
    this.foreground = YounzColors.ink,
    this.icon,
    this.prominent = false,
  });

  final String label;
  final String value;
  final Color background;
  final Color foreground;
  final IconData? icon;
  final bool prominent;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: prominent
          ? const EdgeInsets.all(14)
          : const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: background == Colors.white ? YounzColors.line : background,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: foreground.withValues(alpha: .78),
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: Theme.of(context).textTheme.headlineLarge?.copyWith(
                        color: foreground,
                        fontSize: prominent ? 42 : 28,
                      ),
                ),
              ],
            ),
          ),
          if (icon != null) Icon(icon, color: foreground, size: 23),
        ],
      ),
    );
  }
}

class _HistorySection extends StatelessWidget {
  const _HistorySection({required this.orders});

  final List<OrderSummary> orders;

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      radius: 12,
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Recent History',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              TextButton(
                key: const ValueKey('account-view-all-orders'),
                onPressed: () => _showOrderHistory(context, orders),
                child: const Text('View All'),
              ),
            ],
          ),
          const SizedBox(height: 8),
          if (orders.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 22),
              child: EmptyState(
                title: 'Belum ada pesanan',
                message: 'Riwayat transaksi akun akan tampil di sini.',
              ),
            )
          else
            ...orders.take(4).map(
                  (order) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _PortalOrderCard(order: order),
                  ),
                ),
        ],
      ),
    );
  }
}

class _SettingsList extends StatelessWidget {
  const _SettingsList({required this.portal});

  final PortalData portal;

  @override
  Widget build(BuildContext context) {
    const items = [
      ('profile', 'Profile Information', Icons.person_outline_rounded),
      ('security', 'Security & Passwords', Icons.lock_outline_rounded),
      ('notifications', 'Notifications', Icons.notifications_none_rounded),
      ('help', 'Help Center', Icons.help_outline_rounded),
    ];
    return YounzSurface(
      radius: 12,
      padding: EdgeInsets.zero,
      child: Column(
        children: items.indexed.map((entry) {
          return Column(
            children: [
              ListTile(
                key: ValueKey('account-setting-${entry.$2.$1}'),
                minTileHeight: 56,
                leading: Icon(entry.$2.$3, color: YounzColors.muted),
                title: Text(entry.$2.$2),
                trailing: const Icon(Icons.chevron_right_rounded),
                onTap: () => _openSetting(context, entry.$2.$1),
              ),
              if (entry.$1 != items.length - 1) const Divider(),
            ],
          );
        }).toList(growable: false),
      ),
    );
  }

  void _openSetting(BuildContext context, String id) {
    switch (id) {
      case 'profile':
        _showProfileInformation(context, portal);
        return;
      case 'security':
        _showInformationDialog(
          context,
          title: 'Security & Passwords',
          icon: Icons.lock_outline_rounded,
          message:
              'Sesi akun disimpan secara aman di perangkat. Jangan pernah membagikan password atau kode verifikasi kepada siapa pun.',
        );
        return;
      case 'notifications':
        _showNotificationSettings(context);
        return;
      case 'help':
        _showInformationDialog(
          context,
          title: 'Help Center',
          icon: Icons.support_agent_rounded,
          message:
              'Butuh bantuan terkait pesanan atau akun? Buka Younz AI dari ikon robot atau hubungi operator Younz melalui WhatsApp.',
        );
        return;
    }
  }
}

Future<void> _showOrderHistory(
  BuildContext context,
  List<OrderSummary> orders,
) {
  return showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: const Row(
        children: [
          YounzIconBadge(
            icon: Icons.receipt_long_outlined,
            background: YounzColors.blueWash,
            foreground: YounzColors.blue,
            size: 40,
          ),
          SizedBox(width: 12),
          Expanded(child: Text('Semua Riwayat')),
        ],
      ),
      content: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(dialogContext).height * .56,
        ),
        child: SizedBox(
          width: double.maxFinite,
          child: orders.isEmpty
              ? const Padding(
                  padding: EdgeInsets.symmetric(vertical: 20),
                  child: Text(
                    'Belum ada pesanan yang tersimpan pada akun ini.',
                    textAlign: TextAlign.center,
                  ),
                )
              : ListView.separated(
                  shrinkWrap: true,
                  itemCount: orders.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (_, index) =>
                      _PortalOrderCard(order: orders[index]),
                ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: const Text('Tutup'),
        ),
      ],
    ),
  );
}

Future<void> _showProfileInformation(
  BuildContext context,
  PortalData portal,
) {
  return showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: const Text('Profile Information'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          _ProfileInfoRow(
            icon: Icons.person_outline_rounded,
            label: 'Nama',
            value: portal.name,
          ),
          const Divider(),
          _ProfileInfoRow(
            icon: Icons.mail_outline_rounded,
            label: 'Email',
            value: portal.email,
          ),
          const Divider(),
          _ProfileInfoRow(
            icon: Icons.phone_outlined,
            label: 'Nomor HP',
            value: portal.phone.isEmpty ? 'Belum diisi' : portal.phone,
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: const Text('Tutup'),
        ),
      ],
    ),
  );
}

Future<void> _showInformationDialog(
  BuildContext context, {
  required String title,
  required IconData icon,
  required String message,
}) {
  return showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      icon: Icon(icon, color: YounzColors.blue, size: 34),
      title: Text(title),
      content: Text(message, textAlign: TextAlign.center),
      actionsAlignment: MainAxisAlignment.center,
      actions: [
        FilledButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: const Text('Mengerti'),
        ),
      ],
    ),
  );
}

Future<void> _showNotificationSettings(BuildContext context) {
  final messenger = ScaffoldMessenger.maybeOf(context);
  var orderUpdates = true;
  var promotions = false;
  return showDialog<void>(
    context: context,
    builder: (dialogContext) => StatefulBuilder(
      builder: (context, setDialogState) => AlertDialog(
        title: const Text('Notifications'),
        contentPadding: const EdgeInsets.fromLTRB(14, 14, 14, 4),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SwitchListTile(
              value: orderUpdates,
              title: const Text('Update pesanan'),
              subtitle: const Text('Status dan progres terbaru.'),
              onChanged: (value) => setDialogState(() => orderUpdates = value),
            ),
            SwitchListTile(
              value: promotions,
              title: const Text('Promo & produk baru'),
              subtitle: const Text('Penawaran terbaru dari Younz.'),
              onChanged: (value) => setDialogState(() => promotions = value),
            ),
          ],
        ),
        actions: [
          FilledButton(
            onPressed: () {
              Navigator.of(dialogContext).pop();
              messenger?.showSnackBar(
                const SnackBar(
                    content: Text('Preferensi notifikasi disimpan.')),
              );
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
    ),
  );
}

class _ProfileInfoRow extends StatelessWidget {
  const _ProfileInfoRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: YounzColors.blue),
      title: Text(label),
      subtitle: Text(value),
    );
  }
}

class _PortalOrderCard extends StatelessWidget {
  const _PortalOrderCard({required this.order});

  final OrderSummary order;

  @override
  Widget build(BuildContext context) {
    final completed = order.statusCode == 'completed';
    return YounzSurface(
      padding: EdgeInsets.zero,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          border: Border(
            left: BorderSide(
              width: 7,
              color: completed ? YounzColors.green : YounzColors.blue,
            ),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            LayoutBuilder(
              builder: (context, constraints) {
                final compact = constraints.maxWidth < 320;
                final number = Text(
                  order.orderNumber,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                );
                final status = StatusPill(
                  order.statusLabel,
                  good: completed,
                );
                if (compact) {
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      number,
                      const SizedBox(height: 8),
                      status,
                    ],
                  );
                }
                return Row(
                  children: [
                    Expanded(child: number),
                    const SizedBox(width: 10),
                    status,
                  ],
                );
              },
            ),
            const SizedBox(height: 12),
            Text(
              order.service.isEmpty
                  ? serviceTypeLabel(order.type)
                  : order.service,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 12),
            LayoutBuilder(
              builder: (context, constraints) {
                final compact = constraints.maxWidth < 320;
                final created = Text(
                  'Dibuat ${prettyDate(order.createdAt)}',
                  style: Theme.of(context).textTheme.bodySmall,
                );
                final price = Text(
                  formatRupiah(
                    order.finalPrice ?? order.estimatedPrice,
                  ),
                  style: const TextStyle(
                    color: YounzColors.green,
                    fontWeight: FontWeight.w900,
                  ),
                );
                if (compact) {
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      created,
                      const SizedBox(height: 6),
                      price,
                    ],
                  );
                }
                return Row(
                  children: [
                    Expanded(child: created),
                    const SizedBox(width: 10),
                    price,
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
