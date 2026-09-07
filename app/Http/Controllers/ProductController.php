<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\RequestActionApproval;
use App\Enums\ApprovalType;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RequestActionApproval $requestApproval,
    ) {}

    public function index(): View
    {
        $products = Product::query()
            ->with(['category', 'supplier'])
            ->when(request('q'), fn ($query, string $q) => $query->where(fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")->orWhere('barcode', $q)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower($data['sku']);
        $data['allow_negative_stock'] = $request->boolean('allow_negative_stock');
        $data['is_active'] = $request->boolean('is_active', true);
        $product = Product::create($data);
        $this->audit->log('product.created', $product, after: $product->toArray());

        return back()->with('status', 'Produk berhasil ditambahkan.');
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $before = $product->toArray();
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower($data['sku']);
        $data['allow_negative_stock'] = $request->boolean('allow_negative_stock');
        $data['is_active'] = $request->boolean('is_active', true);
        if ((int) $product->cost_price !== (int) $data['cost_price'] || (int) $product->selling_price !== (int) $data['selling_price']) {
            $approval = $this->requestApproval->handle(
                $request->user(),
                ApprovalType::ProductPriceChange,
                $product,
                ['data' => $data],
                "Perubahan harga produk {$product->name}.",
            );

            return back()->with('status', "Perubahan harga diajukan untuk persetujuan ({$approval->request_number}).");
        }

        $product->update($data);
        $this->audit->log('product.updated', $product, $before, $product->fresh()->toArray());

        return back()->with('status', 'Produk berhasil diperbarui.');
    }
}
