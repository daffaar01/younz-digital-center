<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\KnowledgeDocument;
use App\Models\Product;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $categories = collect(['Kertas', 'Alat Tulis', 'Buku', 'Map dan Arsip', 'Tinta Printer', 'Aksesori Komputer', 'Perlengkapan Sekolah', 'Perlengkapan Kantor', 'Produk Digital', 'Jasa'])
            ->mapWithKeys(fn (string $name) => [$name => Category::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true])]);

        foreach (['Listrik', 'Internet', 'Sewa', 'Gaji', 'Kertas', 'Tinta', 'Perawatan Printer', 'Peralatan Toko', 'Transportasi', 'Domain', 'Hosting', 'Software', 'Lainnya'] as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name]);
        }

        $supplier = Supplier::firstOrCreate(['name' => 'Supplier Utama'], ['contact_name' => 'Admin Supplier', 'is_active' => true]);

        $products = [
            ['Pulpen Gel Hitam', 'ATK-PEN-001', 'Alat Tulis', 2500, 4000, 40, 10],
            ['Kertas HVS A4 80gsm', 'KRT-A4-080', 'Kertas', 55000, 65000, 20, 5],
            ['Map Plastik A4', 'MAP-PLA-001', 'Map dan Arsip', 3000, 5000, 30, 8],
            ['Buku Tulis 38 Lembar', 'BKU-038-001', 'Buku', 3500, 5000, 35, 10],
        ];

        foreach ($products as [$name, $sku, $category, $cost, $price, $stock, $minimum]) {
            $product = Product::firstOrCreate(['sku' => $sku], [
                'category_id' => $categories[$category]->id,
                'supplier_id' => $supplier->id,
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower($sku),
                'unit' => 'pcs',
                'cost_price' => $cost,
                'selling_price' => $price,
                'stock' => $stock,
                'minimum_stock' => $minimum,
                'is_active' => true,
            ]);

            if ($product->wasRecentlyCreated) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'opening_balance',
                    'quantity' => $stock,
                    'stock_before' => 0,
                    'stock_after' => $stock,
                    'reason' => 'Saldo stok awal dari seeder.',
                ]);
            }
        }

        foreach ([
            ['Print Hitam Putih A4', 'print-bw-a4', 'print', 500, 'lembar', 'Print dokumen hitam putih ukuran A4.'],
            ['Print Warna A4', 'print-color-a4', 'print', 2000, 'lembar', 'Print dokumen warna ukuran A4.'],
            ['Fotokopi A4', 'fotokopi-a4', 'fotokopi', 300, 'lembar', 'Fotokopi hitam putih ukuran A4.'],
            ['Scan Dokumen', 'scan-dokumen', 'scan', 2000, 'lembar', 'Scan dokumen ke PDF atau gambar.'],
            ['Jasa Ketik', 'jasa-ketik', 'ketik', 5000, 'halaman', 'Pengetikan dan perapian dokumen.'],
            ['Desain Poster', 'desain-poster', 'desain', 75000, 'desain', 'Desain poster untuk cetak atau media sosial.'],
            ['Website UMKM', 'website-umkm', 'website', 1500000, 'proyek', 'Website profil usaha responsif.'],
        ] as [$name, $slug, $type, $price, $unit, $description]) {
            Service::updateOrCreate(['slug' => $slug], compact('name', 'type', 'unit', 'description') + ['base_price' => $price, 'is_active' => true]);
        }

        foreach ([
            ['Jam Operasional', 'faq', 'Younz Digital Center buka setiap hari pukul 08.00–21.00 WIB.'],
            ['Kebijakan Harga', 'policy', 'Harga yang ditampilkan adalah harga awal. Estimasi AI bukan harga final; operator akan memeriksa file dan spesifikasi sebelum mengonfirmasi harga.'],
            ['Keamanan File', 'privacy', 'File pelanggan disimpan secara privat dan hanya dapat diakses oleh pegawai yang berwenang untuk menangani pesanan.'],
            ['Alur Pesanan Print', 'faq', 'Kirim file, lengkapi spesifikasi, tunggu pemeriksaan operator, setujui estimasi, lalu pantau status hingga siap diambil.'],
        ] as [$title, $type, $content]) {
            KnowledgeDocument::updateOrCreate(['title' => $title], compact('type', 'content') + ['status' => 'active']);
        }
        KnowledgeDocument::query()->where('title', 'Jam Operasional')->update([
            'content' => 'Younz Digital Center buka '.config('services.store.open_hours').'.',
        ]);
        KnowledgeDocument::updateOrCreate(['title' => 'Kontak dan Lokasi'], [
            'type' => 'faq',
            'content' => 'Younz Digital Center beralamat di '.config('services.store.address').
                ' dan dapat dihubungi melalui WhatsApp '.config('services.whatsapp.display_number').'.',
            'status' => 'active',
        ]);

        if (filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOL)) {
            $this->seedUser('Admin Younz', env('SEED_OWNER_EMAIL', 'owner@younz.test'), env('SEED_OWNER_PASSWORD'), UserRole::Owner);
            $this->seedUser('Kasir Younz', env('SEED_CASHIER_EMAIL', 'kasir@younz.test'), env('SEED_CASHIER_PASSWORD'), UserRole::Cashier);
        }
    }

    private function seedUser(string $name, string $email, ?string $password, UserRole $role): void
    {
        if (! $password) {
            $this->command?->warn("Melewati {$email}: password seed belum diatur.");

            return;
        }

        User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make($password),
            'role' => $role,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
