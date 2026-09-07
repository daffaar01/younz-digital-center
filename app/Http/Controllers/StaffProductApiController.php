<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\RequestActionApproval;
use App\Enums\ApprovalType;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StaffProductApiController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RequestActionApproval $requestApproval,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $products = Product::query()->with(['category:id,name', 'supplier:id,name'])
            ->when($request->string('q')->toString(), fn ($query, string $q) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")->orWhere('barcode', $q)))
            ->latest()->paginate(20)->withQueryString();

        return response()->json(['data' => [
            'products' => collect($products->items())->map(fn (Product $product): array => $this->productData($product))->values(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'pagination' => ['current_page' => $products->currentPage(), 'last_page' => $products->lastPage(), 'total' => $products->total()],
        ]]);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower($data['sku']);
        $data['allow_negative_stock'] = $request->boolean('allow_negative_stock');
        $data['is_active'] = $request->boolean('is_active', true);
        $product = Product::create($data);
        $this->audit->log('product.created', $product, after: $product->toArray());

        return response()->json(['message' => 'Produk berhasil ditambahkan.', 'data' => $this->productData($product->load(['category:id,name', 'supplier:id,name']))], 201);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower($data['sku']);
        $data['allow_negative_stock'] = $request->boolean('allow_negative_stock');
        $data['is_active'] = $request->boolean('is_active', true);
        if ((int) $product->cost_price !== (int) $data['cost_price'] || (int) $product->selling_price !== (int) $data['selling_price']) {
            $approval = $this->requestApproval->handle($request->user(), ApprovalType::ProductPriceChange, $product, ['data' => $data], "Perubahan harga produk {$product->name}.");

            return response()->json(['message' => "Perubahan harga diajukan untuk persetujuan ({$approval->request_number}).", 'approval' => ['id' => $approval->id, 'request_number' => $approval->request_number]], 202);
        }
        $before = $product->toArray();
        $product->update($data);
        $this->audit->log('product.updated', $product, $before, $product->fresh()->toArray());

        return response()->json(['message' => 'Produk berhasil diperbarui.']);
    }

    public function requestStock(Request $request, Product $product): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'not_in:0'],
            'type' => ['required', 'in:purchase,adjustment,return_customer,return_supplier,damaged,lost,internal_use'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $approval = $this->requestApproval->handle($request->user(), ApprovalType::StockAdjustment, $product, [
            'quantity' => (int) $data['quantity'], 'movement_type' => $data['type'], 'reason' => $data['reason'],
        ], $data['reason']);

        return response()->json(['message' => "Penyesuaian stok menunggu persetujuan ({$approval->request_number}).", 'approval' => ['id' => $approval->id, 'request_number' => $approval->request_number]], 202);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->isStaff() && $request->user()->tokenCan('staff:products') && $request->user()->hasRole('owner', 'admin'), 403);
    }

    private function productData(Product $product): array
    {
        return [
            'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode,
            'unit' => $product->unit, 'category' => $product->category ? ['id' => $product->category->id, 'name' => $product->category->name] : null,
            'supplier' => $product->supplier ? ['id' => $product->supplier->id, 'name' => $product->supplier->name] : null,
            'cost_price' => $product->cost_price, 'selling_price' => $product->selling_price, 'stock' => $product->stock,
            'minimum_stock' => $product->minimum_stock, 'shelf_location' => $product->shelf_location,
            'allow_negative_stock' => $product->allow_negative_stock, 'is_active' => $product->is_active, 'is_low_stock' => $product->isLowStock(),
        ];
    }
}