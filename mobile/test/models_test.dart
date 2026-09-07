import 'package:flutter_test/flutter_test.dart';

import 'package:younz_digital_center/src/api_client.dart';
import 'package:younz_digital_center/src/models.dart';
import 'package:younz_digital_center/src/theme.dart';

void main() {
  test('formatRupiah uses Indonesian thousands separators', () {
    expect(formatRupiah(125000), 'Rp 125.000');
    expect(formatRupiah(null), 'Belum tersedia');
  });

  test('site payload maps service and store data', () {
    final site = SiteContent.fromJson({
      'services': [
        {
          'id': 3,
          'name': 'Print A4',
          'type': 'print',
          'unit': 'lembar',
          'base_price': 500,
          'description': 'Hitam putih',
        },
      ],
      'featured_products': [],
      'store': {
        'address': 'JL. Kapten Mulyono No. 60C',
        'open_hours': 'Senin - Sabtu',
        'maps_url': '',
        'whatsapp': '628219207240',
      },
    });

    expect(site.services.single.name, 'Print A4');
    expect(site.services.single.basePrice, 500);
    expect(site.store.address, contains('Kapten'));
  });

  test('api client normalizes trailing slash', () {
    final api = ApiClient(baseUrl: 'https://example.test/');
    expect(api.baseUrl, 'https://example.test');
    expect(
      api.uri('/api/v1/site').toString(),
      'https://example.test/api/v1/site',
    );
  });
}
