import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:google_fonts/google_fonts.dart';

import 'navigation.dart';
import 'theme.dart';

class YounzLogo extends StatelessWidget {
  const YounzLogo({super.key, this.inverse = false, this.width = 138});

  final bool inverse;
  final double width;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Younz Digital Center',
      image: true,
      excludeSemantics: true,
      child: SvgPicture.asset(
        inverse
            ? 'assets/brand/younz-wordmark-inverse.svg'
            : 'assets/brand/younz-wordmark-v1.svg',
        width: width,
        fit: BoxFit.contain,
        placeholderBuilder: (_) => Text(
          'YOUNZ',
          style: GoogleFonts.spaceGrotesk(
            color: inverse ? Colors.white : YounzColors.ink,
            fontSize: 22,
            fontWeight: FontWeight.w900,
            letterSpacing: 1.2,
          ),
        ),
      ),
    );
  }
}

class YounzMark extends StatelessWidget {
  const YounzMark({super.key, this.size = 44, this.inverse = false});

  final double size;
  final bool inverse;

  @override
  Widget build(BuildContext context) {
    final background = inverse ? Colors.white : YounzColors.blue;
    final foreground = inverse ? YounzColors.blue : Colors.white;
    return Semantics(
      label: 'Younz',
      image: true,
      excludeSemantics: true,
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(size * .3),
        ),
        child: Stack(
          children: [
            Center(
              child: Text(
                'Y',
                style: GoogleFonts.spaceGrotesk(
                  color: foreground,
                  fontSize: size * .48,
                  fontWeight: FontWeight.w900,
                  height: 1,
                ),
              ),
            ),
            Positioned(
              right: size * .13,
              bottom: size * .16,
              child: Container(
                width: size * .22,
                height: size * .1,
                decoration: BoxDecoration(
                  color: YounzColors.lime,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class YounzTopBar extends StatelessWidget {
  const YounzTopBar({
    super.key,
    this.title,
    this.subtitle,
    this.leading,
    this.actions = const [],
    this.showNavigation = true,
    this.padding = const EdgeInsets.fromLTRB(20, 14, 20, 12),
  });

  final String? title;
  final String? subtitle;
  final Widget? leading;
  final List<Widget> actions;
  final bool showNavigation;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      header: true,
      label: [title, subtitle].whereType<String>().join(', '),
      child: Container(
        constraints: const BoxConstraints(minHeight: 58),
        padding: padding.copyWith(top: 6, bottom: 6),
        decoration: const BoxDecoration(
          color: YounzColors.paper,
          border: Border(bottom: BorderSide(color: YounzColors.controlLine)),
        ),
        child: Stack(
          alignment: Alignment.center,
          children: [
            Align(
              alignment: Alignment.centerLeft,
              child: leading ??
                  (showNavigation
                      ? IconButton(
                          tooltip: 'Menu',
                          onPressed: () {
                            final navigation =
                                YounzNavigationScope.maybeOf(context);
                            if (navigation != null) {
                              navigation.onOpenDrawer();
                              return;
                            }
                            Scaffold.maybeOf(context)?.openDrawer();
                          },
                          icon: const Icon(Icons.menu_rounded),
                        )
                      : const SizedBox(width: 48, height: 48)),
            ),
            Text(
              'Younz Digital',
              maxLines: 1,
              style: GoogleFonts.spaceGrotesk(
                color: YounzColors.primary,
                fontSize: 19,
                fontWeight: FontWeight.w700,
                letterSpacing: -.45,
              ),
            ),
            Align(
              alignment: Alignment.centerRight,
              child: actions.isEmpty
                  ? (showNavigation
                      ? IconButton(
                          tooltip: 'Buka Younz AI',
                          onPressed: () {
                            final navigation =
                                YounzNavigationScope.maybeOf(context);
                            if (navigation != null) {
                              navigation.onOpenAssistant();
                              return;
                            }
                            ScaffoldMessenger.maybeOf(context)?.showSnackBar(
                              const SnackBar(
                                content: Text(
                                  'Younz AI tersedia dari navigasi aplikasi.',
                                ),
                              ),
                            );
                          },
                          icon: const Icon(Icons.smart_toy_outlined),
                        )
                      : const SizedBox(width: 48, height: 48))
                  : Row(mainAxisSize: MainAxisSize.min, children: actions),
            ),
          ],
        ),
      ),
    );
  }
}

class YounzPageHeader extends StatelessWidget {
  const YounzPageHeader({
    super.key,
    required this.eyebrow,
    required this.title,
    required this.description,
    this.icon = Icons.arrow_outward_rounded,
    this.tone = YounzSurfaceTone.blue,
    this.trailing,
  });

  final String eyebrow;
  final String title;
  final String description;
  final IconData icon;
  final YounzSurfaceTone tone;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final dark = tone == YounzSurfaceTone.blue || tone == YounzSurfaceTone.ink;
    final background = switch (tone) {
      YounzSurfaceTone.blue => YounzColors.blue,
      YounzSurfaceTone.ink => YounzColors.ink,
      YounzSurfaceTone.lime => YounzColors.lime,
      YounzSurfaceTone.white => Colors.white,
      YounzSurfaceTone.soft => YounzColors.blueWash,
    };
    final foreground = dark ? Colors.white : YounzColors.ink;
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(YounzRadii.xl),
        border: dark ? null : Border.all(color: YounzColors.line),
      ),
      child: Stack(
        children: [
          Positioned(
            right: -38,
            top: -42,
            child: Container(
              width: 132,
              height: 132,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: dark
                    ? Colors.white.withValues(alpha: .08)
                    : YounzColors.blue.withValues(alpha: .06),
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: dark ? YounzColors.lime : YounzColors.blue,
                      borderRadius: BorderRadius.circular(YounzRadii.sm),
                    ),
                    child: Icon(
                      icon,
                      color: dark ? YounzColors.ink : Colors.white,
                    ),
                  ),
                  const Spacer(),
                  if (trailing != null) trailing!,
                ],
              ),
              const SizedBox(height: 28),
              Eyebrow(eyebrow, light: dark),
              const SizedBox(height: 10),
              Text(
                title,
                style: Theme.of(context).textTheme.headlineLarge?.copyWith(
                      color: foreground,
                      fontSize: 34,
                      height: .98,
                    ),
              ),
              const SizedBox(height: 12),
              Text(
                description,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: dark ? Colors.white : YounzColors.muted,
                    ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class Eyebrow extends StatelessWidget {
  const Eyebrow(this.label, {super.key, this.light = false});

  final String label;
  final bool light;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 18,
          height: 4,
          decoration: BoxDecoration(
            color: light ? YounzColors.lime : YounzColors.blue,
            borderRadius: BorderRadius.circular(2),
          ),
        ),
        const SizedBox(width: 8),
        Flexible(
          child: Text(
            label.toUpperCase(),
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: light ? Colors.white : YounzColors.blue,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 1.35,
                ),
          ),
        ),
      ],
    );
  }
}

class SectionHeading extends StatelessWidget {
  const SectionHeading({
    super.key,
    required this.eyebrow,
    required this.title,
    this.description,
    this.light = false,
    this.trailing,
  });

  final String eyebrow;
  final String title;
  final String? description;
  final bool light;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final color = light ? Colors.white : YounzColors.ink;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Eyebrow(eyebrow, light: light),
        const SizedBox(height: 10),
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: Text(
                title,
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      color: color,
                      fontSize: 29,
                      height: 1.0,
                    ),
              ),
            ),
            if (trailing != null) ...[
              const SizedBox(width: 12),
              trailing!,
            ],
          ],
        ),
        if (description != null) ...[
          const SizedBox(height: 10),
          Text(
            description!,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: light ? Colors.white : YounzColors.muted,
                ),
          ),
        ],
      ],
    );
  }
}

enum YounzSurfaceTone { white, soft, blue, lime, ink }

class YounzSurface extends StatelessWidget {
  const YounzSurface({
    super.key,
    required this.child,
    this.tone = YounzSurfaceTone.white,
    this.padding = const EdgeInsets.all(18),
    this.radius = YounzRadii.lg,
    this.onTap,
    this.border = true,
    this.shadow = false,
  });

  final Widget child;
  final YounzSurfaceTone tone;
  final EdgeInsets padding;
  final double radius;
  final VoidCallback? onTap;
  final bool border;
  final bool shadow;

  Color get _background => switch (tone) {
        YounzSurfaceTone.white => Colors.white,
        YounzSurfaceTone.soft => YounzColors.blueWash,
        YounzSurfaceTone.blue => YounzColors.blue,
        YounzSurfaceTone.lime => YounzColors.lime,
        YounzSurfaceTone.ink => YounzColors.ink,
      };

  @override
  Widget build(BuildContext context) {
    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(radius),
      side: border &&
              (tone == YounzSurfaceTone.white || tone == YounzSurfaceTone.soft)
          ? BorderSide(
              color: onTap == null ? YounzColors.line : YounzColors.controlLine,
            )
          : BorderSide.none,
    );
    final content = Padding(padding: padding, child: child);
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(radius),
        boxShadow: shadow ? YounzElevation.surface : null,
      ),
      child: Material(
        color: _background,
        shape: shape,
        clipBehavior: Clip.antiAlias,
        child: onTap == null ? content : InkWell(onTap: onTap, child: content),
      ),
    );
  }
}

class YounzIconBadge extends StatelessWidget {
  const YounzIconBadge({
    super.key,
    required this.icon,
    this.background = YounzColors.blue,
    this.foreground = Colors.white,
    this.size = 44,
  });

  final IconData icon;
  final Color background;
  final Color foreground;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(size * .3),
      ),
      child: Icon(icon, color: foreground, size: size * .52),
    );
  }
}

class YounzMetric extends StatelessWidget {
  const YounzMetric({
    super.key,
    required this.label,
    required this.value,
    this.accent = YounzColors.blue,
  });

  final String label;
  final String value;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(YounzRadii.md),
          border: Border.all(color: YounzColors.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 18,
              height: 4,
              decoration: BoxDecoration(
                color: accent,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 12),
            Text(value, style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 2),
            Text(label, style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}

class YounzNotice extends StatelessWidget {
  const YounzNotice({
    super.key,
    required this.message,
    this.title,
    this.icon = Icons.info_outline_rounded,
    this.tone = YounzSurfaceTone.lime,
    this.action,
  });

  final String message;
  final String? title;
  final IconData icon;
  final YounzSurfaceTone tone;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      tone: tone,
      border: tone == YounzSurfaceTone.white || tone == YounzSurfaceTone.soft,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          YounzIconBadge(
            icon: icon,
            size: 38,
            background: tone == YounzSurfaceTone.ink
                ? YounzColors.lime
                : YounzColors.ink,
            foreground:
                tone == YounzSurfaceTone.ink ? YounzColors.ink : Colors.white,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (title != null) ...[
                  Text(
                    title!,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          color: tone == YounzSurfaceTone.ink
                              ? Colors.white
                              : YounzColors.ink,
                        ),
                  ),
                  const SizedBox(height: 4),
                ],
                Text(
                  message,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: tone == YounzSurfaceTone.ink
                            ? Colors.white70
                            : YounzColors.ink,
                      ),
                ),
              ],
            ),
          ),
          if (action != null) ...[const SizedBox(width: 8), action!],
        ],
      ),
    );
  }
}

class YounzSheet extends StatelessWidget {
  const YounzSheet({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.fromLTRB(20, 10, 20, 24),
  });

  final Widget child;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: padding.copyWith(
        bottom: padding.bottom + MediaQuery.paddingOf(context).bottom,
      ),
      decoration: const BoxDecoration(
        color: YounzColors.paper,
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 42,
            height: 4,
            margin: const EdgeInsets.only(bottom: 18),
            decoration: BoxDecoration(
              color: YounzColors.line,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          child,
        ],
      ),
    );
  }
}

class LoadingState extends StatelessWidget {
  const LoadingState({super.key, this.label = 'Memuat data Younz...'});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const YounzMark(size: 54),
            const SizedBox(height: 18),
            const SizedBox(
              width: 96,
              child: LinearProgressIndicator(minHeight: 4),
            ),
            const SizedBox(height: 12),
            Text(label, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}

class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: YounzSurface(
          tone: YounzSurfaceTone.ink,
          padding: const EdgeInsets.all(22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const YounzIconBadge(
                icon: Icons.wifi_off_rounded,
                background: YounzColors.lime,
                foreground: YounzColors.ink,
                size: 50,
              ),
              const SizedBox(height: 18),
              Text(
                'Belum tersambung',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      color: Colors.white,
                    ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                style: Theme.of(
                  context,
                ).textTheme.bodyMedium?.copyWith(color: Colors.white70),
              ),
              const SizedBox(height: 18),
              AccentButton(
                label: 'Coba lagi',
                onPressed: onRetry,
                icon: Icons.refresh_rounded,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class AccentButton extends StatelessWidget {
  const AccentButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.expand = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final button = FilledButton.icon(
      onPressed: onPressed,
      icon: Icon(icon ?? Icons.arrow_outward_rounded, size: 18),
      label: Text(label),
      style: FilledButton.styleFrom(
        backgroundColor: YounzColors.lime,
        foregroundColor: YounzColors.ink,
        minimumSize: Size(expand ? double.infinity : 0, 52),
        padding: const EdgeInsets.symmetric(horizontal: 18),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(YounzRadii.md),
        ),
      ),
    );
    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}

class DarkButton extends StatelessWidget {
  const DarkButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.expand = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final button = FilledButton.icon(
      onPressed: onPressed,
      icon: Icon(icon ?? Icons.arrow_outward_rounded, size: 18),
      label: Text(label),
      style: FilledButton.styleFrom(
        backgroundColor: YounzColors.ink,
        foregroundColor: Colors.white,
        minimumSize: Size(expand ? double.infinity : 0, 52),
        padding: const EdgeInsets.symmetric(horizontal: 18),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(YounzRadii.md),
        ),
      ),
    );
    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}

class StatusPill extends StatelessWidget {
  const StatusPill(this.label,
      {super.key, this.good = false, this.dark = false, this.danger = false});

  final String label;
  final bool good;
  final bool dark;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final background = dark
        ? Colors.white.withValues(alpha: .1)
        : danger
            ? YounzColors.dangerWash
            : good
                ? YounzColors.greenWash
                : YounzColors.blueWash;
    final foreground = dark
        ? Colors.white
        : danger
            ? YounzColors.danger
            : good
                ? YounzColors.green
                : YounzColors.blue;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: foreground,
              fontWeight: FontWeight.w900,
              letterSpacing: .3,
            ),
      ),
    );
  }
}

enum TechnicalPatternStyle { dots, grid }

class TechnicalPattern extends StatelessWidget {
  const TechnicalPattern({
    super.key,
    this.style = TechnicalPatternStyle.dots,
    this.color = YounzColors.primary,
    this.opacity = .08,
    this.spacing = 16,
    this.child,
  });

  final TechnicalPatternStyle style;
  final Color color;
  final double opacity;
  final double spacing;
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _TechnicalPatternPainter(
        style: style,
        color: color.withValues(alpha: opacity),
        spacing: spacing,
      ),
      child: child,
    );
  }
}

class _TechnicalPatternPainter extends CustomPainter {
  const _TechnicalPatternPainter({
    required this.style,
    required this.color,
    required this.spacing,
  });

  final TechnicalPatternStyle style;
  final Color color;
  final double spacing;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = 1;
    if (style == TechnicalPatternStyle.dots) {
      for (var x = spacing / 2; x < size.width; x += spacing) {
        for (var y = spacing / 2; y < size.height; y += spacing) {
          canvas.drawCircle(Offset(x, y), 1.1, paint);
        }
      }
      return;
    }
    for (var x = 0.0; x <= size.width; x += spacing) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), paint);
    }
    for (var y = 0.0; y <= size.height; y += spacing) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), paint);
    }
  }

  @override
  bool shouldRepaint(covariant _TechnicalPatternPainter oldDelegate) =>
      oldDelegate.style != style ||
      oldDelegate.color != color ||
      oldDelegate.spacing != spacing;
}

class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.title,
    required this.message,
    this.icon,
    this.action,
  });

  final String title;
  final String message;
  final IconData? icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return YounzSurface(
      child: Column(
        children: [
          YounzIconBadge(
            icon: icon ?? Icons.inbox_outlined,
            background: YounzColors.blueWash,
            foreground: YounzColors.blue,
            size: 52,
          ),
          const SizedBox(height: 14),
          Text(
            title,
            style: Theme.of(context).textTheme.titleLarge,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 6),
          Text(message, textAlign: TextAlign.center),
          if (action != null) ...[const SizedBox(height: 16), action!],
        ],
      ),
    );
  }
}

String serviceTypeLabel(String value) {
  const labels = {
    'print': 'Print & dokumen',
    'fotokopi': 'Fotokopi',
    'scan': 'Scan dokumen',
    'ketik': 'Pengetikan',
    'desain': 'Desain kreatif',
    'website': 'Website',
    'aplikasi': 'Aplikasi',
    'digital': 'Produk digital',
  };
  return labels[value.toLowerCase()] ?? value.replaceAll('_', ' ');
}
