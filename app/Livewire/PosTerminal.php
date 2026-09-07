<?php

namespace App\Livewire;

use App\Actions\Sales\CheckoutSale;
use App\Models\CashSession;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PosTerminal extends Component
{
    public string $search = '';

    /** @var array<int|string, array{id:int,name:string,sku:string,price:int,quantity:int,stock:int}> */
    public array $cart = [];

    public int $discount = 0;

    public string $paymentMethod = 'cash';

    public ?string $lastInvoice = null;

    public ?int $lastSaleId = null;

    public function addProduct(int $productId): void
    {
        $product = Product::query()->where('is_active', true)->findOrFail($productId);
        $key = (string) $product->id;
        $quantity = ($this->cart[$key]['quantity'] ?? 0) + 1;

        if ($quantity > $product->stock && ! $product->allow_negative_stock) {
            $this->addError('cart', "Stok {$product->name} tidak mencukupi.");

            return;
        }

        $this->cart[$key] = [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => $product->selling_price,
            'quantity' => $quantity,
            'stock' => $product->stock,
        ];
    }

    public function decrement(int $productId): void
    {
        $key = (string) $productId;
        if (! isset($this->cart[$key])) {
            return;
        }

        $this->cart[$key]['quantity']--;
        if ($this->cart[$key]['quantity'] < 1) {
            unset($this->cart[$key]);
        }
    }

    public function remove(int $productId): void
    {
        unset($this->cart[(string) $productId]);
    }

    public function checkout(CheckoutSale $checkout): void
    {
        $session = CashSession::query()
            ->where('user_id', auth()->id())
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        if (! $session) {
            throw ValidationException::withMessages(['cash_session' => 'Buka sesi kasir sebelum melakukan transaksi.']);
        }

        $total = $this->total();
        $sale = $checkout->handle(
            auth()->user(),
            collect($this->cart)->map(fn (array $item) => ['product_id' => $item['id'], 'quantity' => $item['quantity']])->values()->all(),
            [['method' => $this->paymentMethod, 'amount' => $total]],
            $this->discount,
            cashSession: $session,
        );

        $this->cart = [];
        $this->discount = 0;
        $this->lastInvoice = $sale->invoice_number;
        $this->lastSaleId = $sale->id;
        session()->flash('status', "Transaksi {$sale->invoice_number} berhasil.");
    }

    public function subtotal(): int
    {
        return collect($this->cart)->sum(fn (array $item) => $item['price'] * $item['quantity']);
    }

    public function total(): int
    {
        return max(0, $this->subtotal() - max(0, $this->discount));
    }

    public function render()
    {
        $products = Product::query()
            ->where('is_active', true)
            ->when($this->search, fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$this->search}%")->orWhere('sku', 'like', "%{$this->search}%")->orWhere('barcode', $this->search)))
            ->orderBy('name')
            ->limit(30)
            ->get();

        return view('livewire.pos-terminal', [
            'products' => $products,
            'subtotal' => $this->subtotal(),
            'total' => $this->total(),
            'cashSession' => CashSession::query()->where('user_id', auth()->id())->where('status', 'open')->latest('opened_at')->first(),
        ]);
    }
}
