<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->order_number }} · Younz Digital Center</title>
    @vite(['resources/css/app.css'])
    <style>@media print {.no-print{display:none!important} body{background:white}}</style>
</head>
<body class="bg-slate-100 p-4 text-slate-800 sm:p-8">
    <main class="mx-auto max-w-3xl rounded-3xl bg-white p-6 shadow-sm sm:p-10">
        <header class="flex flex-col gap-5 border-b border-slate-200 pb-7 sm:flex-row sm:items-start sm:justify-between"><x-brand-logo /><div class="sm:text-right"><p class="font-mono text-xs font-bold tracking-wider text-emerald-700 uppercase">Invoice layanan</p><h1 class="mt-2 font-mono text-xl font-bold">{{ $order->order_number }}</h1><p class="mt-1 text-xs text-slate-500">{{ $order->created_at->translatedFormat('d F Y') }}</p></div></header>
        <section class="grid gap-6 py-7 sm:grid-cols-2"><div><p class="text-xs font-bold uppercase text-slate-400">Ditagihkan kepada</p><p class="mt-2 font-bold">{{ $order->customer_name }}</p><p class="text-sm text-slate-500">{{ $order->customer_phone }}</p></div><div class="sm:text-right"><p class="text-xs font-bold uppercase text-slate-400">Status</p><p class="mt-2 font-bold text-emerald-700">{{ $order->status->label() }}</p></div></section>
        @php($total = $order->final_price ?? $order->estimated_price ?? 0)
        <div class="overflow-hidden rounded-2xl border border-slate-200"><table class="w-full text-sm"><thead class="bg-slate-50"><tr><th class="p-4 text-left">Layanan</th><th class="p-4 text-right">Nilai</th></tr></thead><tbody><tr class="border-t border-slate-200"><td class="p-4">{{ $order->service?->name ?? ucfirst($order->type) }}</td><td class="p-4 text-right">Rp {{ number_format($total, 0, ',', '.') }}</td></tr><tr class="border-t border-slate-200"><td class="p-4 font-bold">Sudah dibayar</td><td class="p-4 text-right font-bold">Rp {{ number_format($order->paid_amount, 0, ',', '.') }}</td></tr><tr class="border-t border-slate-200 bg-[#f5ffd8]"><td class="p-4 font-extrabold">Sisa tagihan</td><td class="p-4 text-right font-extrabold">Rp {{ number_format(max(0, $total - $order->paid_amount), 0, ',', '.') }}</td></tr></tbody></table></div>
        <p class="mt-7 text-xs leading-5 text-slate-500">Dokumen ini adalah ringkasan elektronik pesanan. Konfirmasi pembayaran final dilakukan operator Younz Digital Center.</p>
        <div class="no-print mt-7 flex flex-wrap gap-3"><button onclick="window.print()" class="public-btn-dark min-h-12">Cetak / Simpan PDF</button><a href="{{ route('customer.orders.show', $order) }}" class="public-btn-primary min-h-12">Kembali</a></div>
    </main>
</body>
</html>
