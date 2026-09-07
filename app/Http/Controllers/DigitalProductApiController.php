<?php

namespace App\Http\Controllers;

use App\Http\Requests\DigitalProductRequest;
use App\Jobs\DeleteDigitalProductImage;
use App\Models\DigitalProduct;
use App\Models\ServiceOrder;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DigitalProductApiController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function publicIndex(): JsonResponse
    {
        return response()->json(['data' => DigitalProduct::query()
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->with(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->get()->map(fn (DigitalProduct $product): array => $this->productData($product, false))->values()]);
    }

    public function publicShow(DigitalProduct $digitalProduct): JsonResponse
    {
        abort_unless($digitalProduct->is_active, 404);
        $digitalProduct->load(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')]);

        return response()->json(['data' => $this->productData($digitalProduct, false)]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        return response()->json(['data' => DigitalProduct::query()
            ->orderBy('sort_order')->orderBy('id')
            ->with('variants')
            ->get()->map(fn (DigitalProduct $product): array => $this->productData($product, true))->values()]);
    }

    public function store(DigitalProductRequest $request): JsonResponse
    {
        $upload = $this->storeImage($request->file('image'));

        try {
            $product = DB::transaction(function () use ($request, $upload): DigitalProduct {
                $data = $this->data($request, true);
                $data['image_path'] = $upload['path'];
                $data['image_disk'] = $upload['disk'];
                $data['image_is_upload'] = true;
                $product = DigitalProduct::create($data);
                $this->syncVariants($product, $request->input('variants', []));
                $this->audit->log('digital_product.created', $product, after: $this->auditData($product));

                return $product;
            });
        } catch (\Throwable $exception) {
            Storage::disk($upload['disk'])->delete($upload['path']);
            throw $exception;
        }

        return response()->json(['message' => 'Produk digital berhasil ditambahkan.', 'data' => $this->productData($product, true)], 201);
    }

    public function update(DigitalProductRequest $request, DigitalProduct $digitalProduct): JsonResponse
    {
        $newUpload = $request->hasFile('image') ? $this->storeImage($request->file('image')) : null;
        $oldPath = $digitalProduct->image_is_upload ? $digitalProduct->image_path : null;
        $oldDisk = $digitalProduct->image_disk ?: 'local';
        $before = $this->auditData($digitalProduct);

        try {
            DB::transaction(function () use ($request, $digitalProduct, $newUpload, $before): void {
                $data = $this->data($request);
                if ($newUpload !== null) {
                    $data['image_path'] = $newUpload['path'];
                    $data['image_disk'] = $newUpload['disk'];
                    $data['image_is_upload'] = true;
                }
                $digitalProduct->update($data);
                if ($request->boolean('variants_present') || $request->has('variants')) {
                    $this->syncVariants($digitalProduct, $request->input('variants', []));
                }
                $this->audit->log('digital_product.updated', $digitalProduct, $before, $this->auditData($digitalProduct->fresh()));
            });
        } catch (\Throwable $exception) {
            if ($newUpload !== null) {
                Storage::disk($newUpload['disk'])->delete($newUpload['path']);
            }
            throw $exception;
        }

        if ($newUpload !== null && $oldPath !== null) {
            DeleteDigitalProductImage::dispatch($oldDisk, $oldPath)->afterResponse();
        }

        return response()->json([
            'message' => 'Produk digital berhasil diperbarui.',
            'data' => $this->productData($digitalProduct->fresh()->load('variants'), true),
        ]);
    }

    public function destroy(Request $request, DigitalProduct $digitalProduct): JsonResponse
    {
        $this->authorizeManage($request);
        $path = $digitalProduct->image_is_upload ? $digitalProduct->image_path : null;
        $disk = $digitalProduct->image_disk ?: 'local';
        DB::transaction(function () use ($digitalProduct): void {
            $locked = DigitalProduct::query()->lockForUpdate()->findOrFail($digitalProduct->id);
            $hasActiveReservation = ServiceOrder::query()
                ->where('specifications->digital_product_id', $locked->id)
                ->where('specifications->stock_reserved', true)
                ->where('specifications->stock_released', false)
                ->exists();
            if ($hasActiveReservation) {
                throw ValidationException::withMessages([
                    'product' => 'Produk masih memiliki pesanan dengan stok terreservasi. Nonaktifkan produk sampai pembayaran selesai atau kedaluwarsa.',
                ]);
            }
            $this->audit->log('digital_product.deleted', $locked, $this->auditData($locked));
            $locked->delete();
        });
        if ($path !== null) {
            DeleteDigitalProductImage::dispatch($disk, $path)->afterResponse();
        }

        return response()->json(['message' => 'Produk digital berhasil dihapus.']);
    }

    public function image(DigitalProduct $digitalProduct): Response
    {
        abort_unless($digitalProduct->is_active, 404);

        return $this->imageResponse($digitalProduct, true);
    }

    public function staffImage(Request $request, DigitalProduct $digitalProduct): Response
    {
        $this->authorizeManage($request);

        return $this->imageResponse($digitalProduct, false);
    }

    private function imageResponse(DigitalProduct $digitalProduct, bool $publicCache): Response
    {
        $disk = Storage::disk($digitalProduct->image_disk ?: 'local');
        abort_unless($digitalProduct->image_is_upload && $disk->exists($digitalProduct->image_path), 404);
        $contents = $disk->get($digitalProduct->image_path);
        $extension = strtolower(pathinfo($digitalProduct->image_path, PATHINFO_EXTENSION));

        return response($contents, 200, [
            'Cache-Control' => $publicCache ? 'public, max-age=86400' : 'no-store, private',
            'Vary' => $publicCache ? 'Accept-Encoding' : 'Authorization',
            'Content-Disposition' => 'inline; filename="product-image.'.$extension.'"',
            'Content-Type' => match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                default => 'image/png',
            },
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function storeImage(?UploadedFile $image): array
    {
        abort_unless($image !== null, 422);
        $extension = $image->extension() ?: 'bin';
        $disk = (string) config('filesystems.digital_products_disk', 'local');
        abort_unless(in_array($disk, ['local', 'r2'], true), 500, 'Disk foto produk tidak valid.');
        $path = $image->storeAs('digital-products', Str::uuid().'.'.$extension, $disk);
        abort_if($path === false, 500, 'Foto produk gagal disimpan.');

        return ['disk' => $disk, 'path' => $path];
    }

    private function data(DigitalProductRequest $request, bool $creating = false): array
    {
        $data = $request->safe()->except(['image', 'variants', 'variants_present']);
        if ($creating || $request->has('stock')) {
            $data['stock'] = $request->filled('stock') ? (int) $request->input('stock') : null;
        } else {
            unset($data['stock']);
        }
        if ($creating || $request->has('price')) {
            $data['price'] = $request->filled('price') ? (int) $request->input('price') : null;
        } else {
            unset($data['price']);
        }
        if ($creating || $request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active', true);
        } else {
            unset($data['is_active']);
        }
        if ($creating || $request->has('mark')) {
            $data['mark'] = $request->filled('mark') ? Str::upper($request->string('mark')->toString()) : null;
        } else {
            unset($data['mark']);
        }

        return $data;
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->isStaff() && $request->user()->tokenCan('staff:products') && $request->user()->hasRole('owner', 'admin'), 403);
    }

    private function productData(DigitalProduct $product, bool $staff): array
    {
        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'category' => $product->category,
            'mark' => $product->mark,
            'image_url' => $product->imageUrl($staff),
            'stock' => $product->stock,
            'stock_label' => $product->stockLabel(),
            'price' => $product->price,
            'price_label' => $product->priceLabel(),
            'description' => $product->description,
            'sort_order' => $product->sort_order,
            'variants' => $product->relationLoaded('variants')
                ? $product->variants->map(fn ($variant): array => [
                    'id' => $variant->id,
                    'label' => $variant->label,
                    'price' => $variant->price,
                    'price_label' => $variant->priceLabel(),
                    'stock' => $variant->stock,
                    'stock_label' => $variant->stockLabel(),
                    ...($staff ? ['is_active' => $variant->is_active] : []),
                    'sort_order' => $variant->sort_order,
                ])->values()->all()
                : [],
        ];
        if ($staff) {
            $data['is_active'] = $product->is_active;
            $data['image_is_upload'] = $product->image_is_upload;
        }

        return $data;
    }

    private function syncVariants(DigitalProduct $product, array $variants): void
    {
        $kept = [];
        foreach ($variants as $variant) {
            $variantId = filled($variant['id'] ?? null) ? (int) $variant['id'] : null;
            $model = $variantId ? $product->variants()->find($variantId) : null;
            if ($variantId && ! $model) {
                throw ValidationException::withMessages(['variants' => 'Varian tidak valid untuk produk ini.']);
            }
            $model ??= $product->variants()->make();
            $model->fill([
                'label' => trim((string) $variant['label']),
                'price' => filled($variant['price'] ?? null) ? (int) $variant['price'] : null,
                'stock' => filled($variant['stock'] ?? null) ? (int) $variant['stock'] : null,
                'is_active' => filter_var($variant['is_active'] ?? true, FILTER_VALIDATE_BOOL),
                'sort_order' => (int) $variant['sort_order'],
            ])->save();
            $kept[] = $model->id;
        }
        $product->variants()->whereNotIn('id', $kept)->update(['is_active' => false]);
        $product->load('variants');
    }

    private function auditData(DigitalProduct $product): array
    {
        return [
            'name' => $product->name,
            'category' => $product->category,
            'mark' => $product->mark,
            'description' => $product->description,
            'stock' => $product->stock,
            'price' => $product->price,
            'is_active' => $product->is_active,
            'sort_order' => $product->sort_order,
            'image_is_upload' => $product->image_is_upload,
            'image_identifier' => $product->image_is_upload ? basename($product->image_path) : $product->image_path,
            'image_disk' => $product->image_is_upload ? $product->image_disk : null,
        ];
    }
}
