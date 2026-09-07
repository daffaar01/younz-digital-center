import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import 'api_client.dart';
import 'models.dart';
import 'theme.dart';

class DigitalProductArtwork extends StatelessWidget {
  const DigitalProductArtwork({
    super.key,
    required this.product,
    required this.api,
    this.aspectRatio = 16 / 10,
    this.borderRadius = const BorderRadius.all(
      Radius.circular(YounzRadii.lg),
    ),
  });

  final DigitalProduct product;
  final ApiClient api;
  final double aspectRatio;
  final BorderRadius borderRadius;

  @override
  Widget build(BuildContext context) {
    final localAsset = _localAssetFor(product);
    final imageUrl = product.imageUrl.trim();
    Widget image;

    if (localAsset != null && (imageUrl.isEmpty || _isSvg(imageUrl))) {
      image = SvgPicture.asset(localAsset, fit: BoxFit.cover);
    } else if (imageUrl.isNotEmpty && !_isSvg(imageUrl)) {
      image = Image.network(
        api.resolveUrl(imageUrl),
        fit: BoxFit.cover,
        filterQuality: FilterQuality.medium,
        errorBuilder: (_, __, ___) => _ProductFallback(product: product),
      );
    } else {
      image = _ProductFallback(product: product);
    }

    return Semantics(
      image: true,
      label: 'Gambar produk ${product.name}',
      excludeSemantics: true,
      child: ClipRRect(
        borderRadius: borderRadius,
        child: AspectRatio(
          aspectRatio: aspectRatio,
          child: ColoredBox(
            color: YounzColors.blueWash,
            child: image,
          ),
        ),
      ),
    );
  }
}

bool _isSvg(String value) {
  final path = Uri.tryParse(value)?.path.toLowerCase() ?? value.toLowerCase();
  return path.endsWith('.svg');
}

String? _localAssetFor(DigitalProduct product) {
  final imagePath = Uri.tryParse(product.imageUrl)?.path.toLowerCase() ?? '';
  final name = product.name.toLowerCase();

  if (imagePath.endsWith('/youtube-premium.svg') ||
      name.contains('youtube premium')) {
    return 'assets/products/youtube-premium.svg';
  }
  if (imagePath.endsWith('/video-premium.svg') ||
      name.contains('video premium')) {
    return 'assets/products/video-premium.svg';
  }
  if (imagePath.endsWith('/discord-nitro.svg') || name.contains('discord')) {
    return 'assets/products/discord-nitro.svg';
  }
  if (imagePath.endsWith('/claude-ai.svg') || name.contains('claude')) {
    return 'assets/products/claude-ai.svg';
  }
  if (imagePath.endsWith('/chatgpt.svg') || name.contains('chatgpt')) {
    return 'assets/products/chatgpt.svg';
  }
  if (imagePath.endsWith('/leonardo-ai.svg') || name.contains('leonardo')) {
    return 'assets/products/leonardo-ai.svg';
  }
  if (imagePath.endsWith('/grok.svg') || name == 'grok') {
    return 'assets/products/grok.svg';
  }
  return null;
}

class _ProductFallback extends StatelessWidget {
  const _ProductFallback({required this.product});

  final DigitalProduct product;

  @override
  Widget build(BuildContext context) {
    final mark = product.mark.trim().isEmpty ? 'YD' : product.mark.trim();
    final accent = switch (product.category.toLowerCase()) {
      final category when category.contains('kreatif') => YounzColors.lime,
      final category when category.contains('streaming') =>
        YounzColors.electricBlue,
      final category when category.contains('komunitas') => YounzColors.green,
      _ => YounzColors.blue,
    };

    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [accent, YounzColors.ink],
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            right: -32,
            top: -38,
            child: Container(
              width: 150,
              height: 150,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: YounzColors.lime.withValues(alpha: .72),
                  width: 20,
                ),
              ),
            ),
          ),
          Positioned(
            left: 22,
            bottom: 20,
            child: Text(
              mark,
              style: Theme.of(context).textTheme.displayMedium?.copyWith(
                    color: Colors.white,
                    fontSize: 48,
                    letterSpacing: -2,
                  ),
            ),
          ),
          Positioned(
            left: 24,
            top: 22,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
              decoration: BoxDecoration(
                color: YounzColors.lime,
                borderRadius: BorderRadius.circular(YounzRadii.sm),
              ),
              child: Text(
                product.category.toUpperCase(),
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: YounzColors.ink,
                      letterSpacing: .4,
                    ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
