import 'models.dart';

bool digitalProductIsAvailable(DigitalProduct product) {
  if (product.variants.isNotEmpty) {
    return product.variants.any((variant) => variant.stock != 0);
  }
  return product.stock != 0;
}
