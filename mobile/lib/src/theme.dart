import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

abstract final class YounzColors {
  // Stitch's editorial palette: warm paper, electric blue, ink, and lime.
  static const primary = Color(0xFF0041D0);
  static const blue = Color(0xFF1857FF);
  static const electricBlue = Color(0xFF246BFF);
  static const lime = Color(0xFFBDF128);
  static const brandLime = Color(0xFFCAFF38);
  static const ink = Color(0xFF070707);
  static const dark = Color(0xFF1C1B1B);
  static const paper = Color(0xFFFCF8F8);
  static const muted = Color(0xFF434656);
  static const line = Color(0xFFEAECE6);
  static const controlLine = Color(0xFFC3C5D9);
  static const outline = Color(0xFF737688);
  static const green = Color(0xFF006C49);

  // Soft tints keep the palette recognizable without turning every surface blue.
  static const blueWash = Color(0xFFE7E9FF);
  static const limeWash = Color(0xFFF1FBCB);
  static const greenWash = Color(0xFFE8F7EF);
  static const danger = Color(0xFFB42318);
  static const dangerWash = Color(0xFFFFEFED);
}

abstract final class YounzSpace {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const lg = 16.0;
  static const xl = 24.0;
  static const xxl = 32.0;
  static const section = 40.0;
}

abstract final class YounzRadii {
  static const sm = 12.0;
  static const md = 18.0;
  static const lg = 24.0;
  static const xl = 32.0;
}

abstract final class YounzElevation {
  static const surface = <BoxShadow>[
    BoxShadow(
      color: Color(0x12070F12),
      blurRadius: 22,
      offset: Offset(0, 10),
    ),
  ];

  static const floating = <BoxShadow>[
    BoxShadow(
      color: Color(0x22070F12),
      blurRadius: 28,
      offset: Offset(0, 14),
    ),
  ];
}

ThemeData buildYounzTheme() {
  final base = GoogleFonts.dmSansTextTheme();
  final display = GoogleFonts.spaceGroteskTextTheme(base);
  final scheme = ColorScheme.fromSeed(
    seedColor: YounzColors.primary,
    brightness: Brightness.light,
  ).copyWith(
    primary: YounzColors.primary,
    onPrimary: Colors.white,
    secondary: YounzColors.lime,
    onSecondary: YounzColors.ink,
    surface: YounzColors.paper,
    onSurface: YounzColors.ink,
    error: YounzColors.danger,
  );

  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    colorScheme: scheme,
    scaffoldBackgroundColor: YounzColors.paper,
    splashFactory: InkSparkle.splashFactory,
    visualDensity: VisualDensity.standard,
    textTheme: base.copyWith(
      displayLarge: display.displayLarge?.copyWith(
        color: YounzColors.ink,
        fontWeight: FontWeight.w700,
        letterSpacing: -2.8,
        height: .95,
      ),
      displayMedium: display.displayMedium?.copyWith(
        color: YounzColors.ink,
        fontWeight: FontWeight.w700,
        letterSpacing: -2.0,
        height: .98,
      ),
      displaySmall: display.displaySmall?.copyWith(
        color: YounzColors.ink,
        fontWeight: FontWeight.w700,
        letterSpacing: -1.4,
        height: 1.0,
      ),
      headlineLarge: display.headlineLarge?.copyWith(
        color: YounzColors.ink,
        fontWeight: FontWeight.w700,
        letterSpacing: -1.25,
        height: 1.02,
      ),
      headlineMedium: display.headlineMedium?.copyWith(
        color: YounzColors.ink,
        fontWeight: FontWeight.w700,
        letterSpacing: -.9,
        height: 1.05,
      ),
      headlineSmall: display.headlineSmall?.copyWith(
        color: YounzColors.ink,
        fontWeight: FontWeight.w700,
        letterSpacing: -.5,
        height: 1.08,
      ),
      titleLarge: display.titleLarge?.copyWith(
        color: YounzColors.ink,
        fontWeight: FontWeight.w700,
        letterSpacing: -.35,
      ),
      titleMedium: base.titleMedium?.copyWith(
        color: YounzColors.ink,
        fontWeight: FontWeight.w700,
        letterSpacing: -.15,
      ),
      titleSmall: base.titleSmall?.copyWith(
        color: YounzColors.ink,
        fontWeight: FontWeight.w700,
      ),
      bodyLarge: base.bodyLarge?.copyWith(
        color: YounzColors.ink,
        height: 1.45,
      ),
      bodyMedium: base.bodyMedium?.copyWith(
        color: YounzColors.muted,
        height: 1.45,
      ),
      bodySmall: base.bodySmall?.copyWith(
        color: YounzColors.muted,
        height: 1.35,
      ),
      labelLarge: base.labelLarge?.copyWith(
        fontWeight: FontWeight.w700,
        letterSpacing: .05,
      ),
      labelMedium: base.labelMedium?.copyWith(
        fontWeight: FontWeight.w700,
        letterSpacing: .2,
      ),
      labelSmall: base.labelSmall?.copyWith(
        fontWeight: FontWeight.w800,
        letterSpacing: .7,
      ),
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: Colors.transparent,
      foregroundColor: YounzColors.ink,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      toolbarHeight: 68,
      titleTextStyle: GoogleFonts.spaceGrotesk(
        color: YounzColors.ink,
        fontSize: 22,
        fontWeight: FontWeight.w700,
        letterSpacing: -.6,
      ),
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(YounzRadii.lg),
        side: const BorderSide(color: YounzColors.line),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(YounzRadii.md),
        borderSide: const BorderSide(color: YounzColors.line),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(YounzRadii.md),
        borderSide: const BorderSide(color: YounzColors.line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(YounzRadii.md),
        borderSide: const BorderSide(color: YounzColors.blue, width: 1.6),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(YounzRadii.md),
        borderSide: const BorderSide(color: YounzColors.danger, width: 1.2),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(YounzRadii.md),
        borderSide: const BorderSide(color: YounzColors.danger, width: 1.4),
      ),
      labelStyle: const TextStyle(color: YounzColors.muted),
      floatingLabelStyle: const TextStyle(color: YounzColors.blue),
      prefixIconColor: YounzColors.muted,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size.fromHeight(52),
        padding: const EdgeInsets.symmetric(horizontal: 18),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(YounzRadii.md),
        ),
        backgroundColor: YounzColors.blue,
        foregroundColor: Colors.white,
        textStyle: const TextStyle(fontWeight: FontWeight.w800),
      ),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        minimumSize: const Size.fromHeight(52),
        padding: const EdgeInsets.symmetric(horizontal: 18),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(YounzRadii.md),
        ),
        backgroundColor: YounzColors.blue,
        foregroundColor: Colors.white,
        elevation: 0,
        textStyle: const TextStyle(fontWeight: FontWeight.w800),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(52),
        padding: const EdgeInsets.symmetric(horizontal: 18),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(YounzRadii.md),
        ),
        side: const BorderSide(color: YounzColors.controlLine),
        foregroundColor: YounzColors.ink,
        textStyle: const TextStyle(fontWeight: FontWeight.w800),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: YounzColors.blue,
        textStyle: const TextStyle(fontWeight: FontWeight.w800),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      height: 76,
      backgroundColor: Colors.white,
      elevation: 0,
      indicatorColor: YounzColors.lime,
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      labelTextStyle: WidgetStatePropertyAll(
        GoogleFonts.dmSans(fontSize: 10, fontWeight: FontWeight.w800),
      ),
    ),
    bottomSheetTheme: const BottomSheetThemeData(
      backgroundColor: Colors.transparent,
      surfaceTintColor: Colors.transparent,
      showDragHandle: false,
    ),
    chipTheme: ChipThemeData(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(YounzRadii.sm),
      ),
      side: const BorderSide(color: YounzColors.controlLine),
      backgroundColor: Colors.white,
      selectedColor: YounzColors.blueWash,
      labelStyle: const TextStyle(fontWeight: FontWeight.w800),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    ),
    dividerTheme: const DividerThemeData(
      color: YounzColors.line,
      thickness: 1,
      space: 1,
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: YounzColors.ink,
      contentTextStyle: GoogleFonts.dmSans(
        color: Colors.white,
        fontWeight: FontWeight.w600,
      ),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(YounzRadii.md),
      ),
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(
      color: YounzColors.blue,
      linearTrackColor: YounzColors.blueWash,
    ),
  );
}

String formatRupiah(num? value) {
  if (value == null) return 'Belum tersedia';
  final digits = value.round().toString();
  final buffer = StringBuffer();
  for (var index = 0; index < digits.length; index++) {
    if (index > 0 && (digits.length - index) % 3 == 0) buffer.write('.');
    buffer.write(digits[index]);
  }
  return 'Rp ${buffer.toString()}';
}

String prettyDate(String? value) {
  if (value == null || value.isEmpty) return 'Belum ditentukan';
  final date = DateTime.tryParse(value)?.toLocal();
  if (date == null) return value;
  final day = date.day.toString().padLeft(2, '0');
  final month = date.month.toString().padLeft(2, '0');
  return '$day/$month/${date.year} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
}
