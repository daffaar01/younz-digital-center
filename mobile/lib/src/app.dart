import 'package:flutter/material.dart';

import 'api_client.dart';
import 'screens/account_screen.dart';
import 'screens/ai_chat_screen.dart';
import 'screens/home_screen.dart';
import 'screens/products_screen.dart';
import 'screens/services_screen.dart';
import 'screens/topup_screen.dart';
import 'screens/track_screen.dart';
import 'navigation.dart';
import 'theme.dart';
import 'widgets.dart';

class YounzApp extends StatelessWidget {
  const YounzApp({super.key, this.api});

  final ApiClient? api;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Younz Digital Center',
      debugShowCheckedModeBanner: false,
      theme: buildYounzTheme(),
      home: AuthGate(api: api),
    );
  }
}

/// Decides whether the user sees the login page or the main application shell.
/// A valid stored session is reused so returning users do not have to log in
/// again on every launch.
class AuthGate extends StatefulWidget {
  const AuthGate({super.key, this.api});

  final ApiClient? api;

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  late final ApiClient _api;
  bool? _hasSession;

  @override
  void initState() {
    super.initState();
    _api = widget.api ?? ApiClient();
    _checkSession();
  }

  Future<void> _checkSession() async {
    final active = await _api.hasSession;
    if (!mounted) return;
    setState(() => _hasSession = active);
  }

  void _authenticated() {
    if (!mounted) return;
    setState(() => _hasSession = true);
  }

  void _loggedOut() {
    if (!mounted) return;
    setState(() => _hasSession = false);
  }

  @override
  Widget build(BuildContext context) {
    final session = _hasSession;
    if (session == null) {
      return const Scaffold(
        backgroundColor: YounzColors.paper,
        body: LoadingState(label: 'Memeriksa sesi akun...'),
      );
    }
    if (session) return HomeShell(api: _api, onLoggedOut: _loggedOut);
    return AccountScreen(
      api: _api,
      showNavigation: false,
      onAuthenticated: _authenticated,
    );
  }
}

class HomeShell extends StatefulWidget {
  const HomeShell({super.key, this.api, this.onLoggedOut});

  final ApiClient? api;
  final VoidCallback? onLoggedOut;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  late final ApiClient _api;
  final _scaffoldKey = GlobalKey<ScaffoldState>();
  int _index = 0;

  @override
  void initState() {
    super.initState();
    _api = widget.api ?? ApiClient();
  }

  void _navigate(int index) {
    if (!mounted || index == _index) return;
    setState(() => _index = index);
  }

  void _selectDestination(int index) {
    _scaffoldKey.currentState?.closeDrawer();
    _navigate(index);
  }

  void _openDrawer() => _scaffoldKey.currentState?.openDrawer();

  void _openAssistant() {
    _scaffoldKey.currentState?.closeDrawer();
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => AiChatScreen(api: _api)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final screens = [
      HomeScreen(api: _api, onNavigate: _navigate),
      ServicesScreen(api: _api),
      ProductsScreen(api: _api),
      TopupScreen(api: _api),
      TrackScreen(api: _api),
      AccountScreen(api: _api, onLoggedOut: widget.onLoggedOut),
    ];

    return YounzNavigationScope(
      onNavigate: _navigate,
      onOpenDrawer: _openDrawer,
      onOpenAssistant: _openAssistant,
      child: Scaffold(
        key: _scaffoldKey,
        backgroundColor: YounzColors.paper,
        extendBody: true,
        drawer: _YounzDrawer(
          selectedIndex: _index,
          onDestinationSelected: _selectDestination,
          onAssistant: _openAssistant,
        ),
        body: SafeArea(
          bottom: false,
          child: IndexedStack(index: _index, children: screens),
        ),
        bottomNavigationBar: _YounzDock(
          selectedIndex: _index,
          onSelected: _navigate,
        ),
      ),
    );
  }
}

class _YounzDrawer extends StatelessWidget {
  const _YounzDrawer({
    required this.selectedIndex,
    required this.onDestinationSelected,
    required this.onAssistant,
  });

  final int selectedIndex;
  final ValueChanged<int> onDestinationSelected;
  final VoidCallback onAssistant;

  @override
  Widget build(BuildContext context) {
    const destinations = [
      ('Beranda', Icons.home_rounded, YounzDestination.home),
      ('Layanan', Icons.grid_view_rounded, YounzDestination.services),
      ('Produk', Icons.shopping_bag_rounded, YounzDestination.products),
      ('Top Up', Icons.bolt_rounded, YounzDestination.topup),
      ('Lacak pesanan', Icons.radar_rounded, YounzDestination.track),
      ('Akun saya', Icons.person_rounded, YounzDestination.account),
    ];

    return Drawer(
      backgroundColor: YounzColors.paper,
      child: SafeArea(
        child: Column(
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(22, 20, 14, 22),
              decoration: const BoxDecoration(color: YounzColors.blue),
              child: Row(
                children: [
                  const Expanded(child: YounzLogo(inverse: true, width: 148)),
                  IconButton(
                    tooltip: 'Tutup menu',
                    onPressed: () => Navigator.of(context).pop(),
                    color: Colors.white,
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(12, 18, 12, 12),
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
                    child: Text(
                      'NAVIGASI',
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                            color: YounzColors.muted,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 1.3,
                          ),
                    ),
                  ),
                  ...destinations.map((destination) {
                    final selected = selectedIndex == destination.$3;
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 4),
                      child: ListTile(
                        key: ValueKey('drawer-${destination.$1}'),
                        selected: selected,
                        selectedTileColor: YounzColors.lime,
                        selectedColor: YounzColors.ink,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        leading: Icon(destination.$2),
                        title: Text(destination.$1),
                        trailing: selected
                            ? const Icon(Icons.check_rounded, size: 19)
                            : const Icon(Icons.chevron_right_rounded),
                        onTap: () => onDestinationSelected(destination.$3),
                      ),
                    );
                  }),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 18),
              child: YounzSurface(
                key: const ValueKey('drawer-younz-ai'),
                tone: YounzSurfaceTone.ink,
                border: false,
                padding: const EdgeInsets.all(14),
                onTap: onAssistant,
                child: Row(
                  children: [
                    const YounzIconBadge(
                      icon: Icons.smart_toy_outlined,
                      background: YounzColors.lime,
                      foreground: YounzColors.ink,
                      size: 40,
                    ),
                    const SizedBox(width: 11),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Younz AI',
                            style: Theme.of(context)
                                .textTheme
                                .titleSmall
                                ?.copyWith(color: Colors.white),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Tanya kebutuhanmu',
                            style: Theme.of(context)
                                .textTheme
                                .bodySmall
                                ?.copyWith(color: Colors.white70),
                          ),
                        ],
                      ),
                    ),
                    const Icon(
                      Icons.arrow_outward_rounded,
                      color: YounzColors.lime,
                      size: 19,
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _YounzDock extends StatelessWidget {
  const _YounzDock({required this.selectedIndex, required this.onSelected});

  final int selectedIndex;
  final ValueChanged<int> onSelected;

  @override
  Widget build(BuildContext context) {
    const destinations = [
      ('Beranda', Icons.home_outlined, Icons.home_rounded),
      ('Layanan', Icons.grid_view_outlined, Icons.grid_view_rounded),
      ('Produk', Icons.shopping_bag_outlined, Icons.shopping_bag_rounded),
      ('Top Up', Icons.bolt_outlined, Icons.bolt_rounded),
      ('Lacak', Icons.radar_outlined, Icons.radar_rounded),
      ('Akun', Icons.person_outline_rounded, Icons.person_rounded),
    ];
    return SafeArea(
      minimum: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      child: Container(
        height: 68,
        padding: const EdgeInsets.all(5),
        decoration: BoxDecoration(
          color: YounzColors.ink,
          borderRadius: BorderRadius.circular(14),
          boxShadow: YounzElevation.floating,
        ),
        child: Row(
          children: destinations.indexed.map((entry) {
            final index = entry.$1;
            final destination = entry.$2;
            final selected = selectedIndex == index;
            return Expanded(
              child: Semantics(
                selected: selected,
                button: true,
                label: destination.$1,
                excludeSemantics: true,
                onTap: () => onSelected(index),
                child: Material(
                  color: Colors.transparent,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(11),
                    onTap: () => onSelected(index),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 240),
                      curve: Curves.easeOutCubic,
                      decoration: BoxDecoration(
                        color: selected
                            ? YounzColors.brandLime
                            : Colors.transparent,
                        borderRadius: BorderRadius.circular(11),
                      ),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            selected ? destination.$3 : destination.$2,
                            size: 22,
                            color: selected ? YounzColors.ink : Colors.white70,
                          ),
                          const SizedBox(height: 3),
                          SizedBox(
                            width: double.infinity,
                            height: 18,
                            child: FittedBox(
                              fit: BoxFit.scaleDown,
                              child: Text(
                                destination.$1,
                                style: Theme.of(context)
                                    .textTheme
                                    .labelSmall
                                    ?.copyWith(
                                      color: selected
                                          ? YounzColors.ink
                                          : Colors.white70,
                                      fontSize: 10,
                                      letterSpacing: 0,
                                    ),
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
          }).toList(),
        ),
      ),
    );
  }
}
