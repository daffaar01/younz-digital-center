import 'package:flutter/widgets.dart';

/// Shared navigation callbacks for screens that live inside [HomeShell].
///
/// Each tab has its own nested [Scaffold], so looking up a drawer through
/// `Scaffold.of(context)` would otherwise stop at the tab scaffold instead of
/// reaching the shell scaffold. This scope keeps those actions connected to
/// the app-level navigation surface.
class YounzNavigationScope extends InheritedWidget {
  const YounzNavigationScope({
    super.key,
    required this.onNavigate,
    required this.onOpenDrawer,
    required this.onOpenAssistant,
    required super.child,
  });

  final ValueChanged<int> onNavigate;
  final VoidCallback onOpenDrawer;
  final VoidCallback onOpenAssistant;

  static YounzNavigationScope? maybeOf(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<YounzNavigationScope>();

  @override
  bool updateShouldNotify(YounzNavigationScope oldWidget) =>
      onNavigate != oldWidget.onNavigate ||
      onOpenDrawer != oldWidget.onOpenDrawer ||
      onOpenAssistant != oldWidget.onOpenAssistant;
}

abstract final class YounzDestination {
  static const home = 0;
  static const services = 1;
  static const products = 2;
  static const topup = 3;
  static const track = 4;
  static const account = 5;
}
