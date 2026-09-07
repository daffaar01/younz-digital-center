<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceOrderRequest;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicController extends Controller
{
    public function home(): View
    {
        return view('public.home', [
            'services' => Service::query()->where('is_active', true)->orderBy('type')->limit(12)->get(),
            'featuredProducts' => Product::query()->where('is_active', true)->where('stock', '>', 0)->limit(8)->get(),
            'testimonials' => Testimonial::query()->published()->orderBy('display_order')->latest()->limit(6)->get(),
            'title' => 'Younz Digital Center | Print, Desain, Website & Layanan Digital',
            'description' => 'Pesan layanan print, fotokopi, scan, desain, website, aplikasi, ATK, dan top up di Younz Digital Center. Pantau status pesanan secara online.',
        ]);
    }

    public function privacy(): View
    {
        return view('public.legal', ['document' => 'privacy']);
    }

    public function terms(): View
    {
        return view('public.legal', ['document' => 'terms']);
    }

    public function serviceWorker(): BinaryFileResponse
    {
        $response = response()->file(resource_path('js/service-worker.js'), [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
        $response->setPrivate();

        return $response;
    }

    public function sitemap(): Response
    {
        $urls = collect([
            [route('home'), 'weekly', '1.0'],
            [route('public.order'), 'weekly', '0.9'],
            [route('topup.index'), 'daily', '0.8'],
            [route('public.track'), 'monthly', '0.6'],
            [route('customer.login'), 'monthly', '0.5'],
            [route('customer.register'), 'monthly', '0.5'],
            [route('privacy'), 'yearly', '0.3'],
            [route('terms'), 'yearly', '0.3'],
        ])->map(fn (array $page) => '<url><loc>'.e($page[0]).'</loc><changefreq>'.$page[1].'</changefreq><priority>'.$page[2].'</priority></url>')->implode('');

        return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>')
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function order(): View
    {
        return view('public.order', [
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'title' => 'Pesan Layanan | Younz Digital Center',
            'description' => 'Kirim kebutuhan dan file Anda ke Younz Digital Center. Operator memeriksa spesifikasi, mengonfirmasi estimasi, lalu Anda dapat memantau progres.',
        ]);
    }

    public function storeOrder(ServiceOrderRequest $request, ServiceOrderController $orders): RedirectResponse
    {
        $order = $orders->createOrder($request);

        if ($request->user() && ! $request->user()->isStaff()) {
            return redirect()->route('customer.orders.show', $order)
                ->with('status', 'Pesanan diterima dan sudah masuk ke akun Anda.');
        }

        return redirect()->route('public.track.show', $order->public_token)
            ->with('status', 'Pesanan diterima. Simpan tautan ini untuk memantau status.');
    }

    public function track(Request $request): View
    {
        $order = null;
        $attempted = $request->isMethod('post');

        if ($attempted) {
            $validated = $request->validate([
                'phone' => ['required', 'string', 'max:30'],
                'order_number' => ['required', 'string', 'max:30'],
            ]);

            $order = ServiceOrder::query()
                ->where('order_number', mb_strtoupper($validated['order_number']))
                ->where('customer_phone', preg_replace('/\D+/', '', $validated['phone']))
                ->first();
        }

        return view('public.track', compact('order', 'attempted'));
    }

    public function showTrack(string $token): View
    {
        $order = ServiceOrder::query()->where('public_token', $token)->firstOrFail();
        $attempted = true;

        return view('public.track', compact('order', 'attempted'));
    }

    public function myOrders(Request $request): View
    {
        $orders = collect();
        $phone = '';
        $orderNumber = '';
        $attempted = $request->isMethod('post');

        if ($attempted) {
            $validated = $request->validate([
                'phone' => ['required', 'string', 'max:30'],
                'order_number' => ['required', 'string', 'max:30'],
            ]);
            $phone = preg_replace('/\D+/', '', $validated['phone']);
            $orderNumber = mb_strtoupper($validated['order_number']);
            $orders = ServiceOrder::query()
                ->where('customer_phone', $phone)
                ->where('order_number', $orderNumber)
                ->limit(1)
                ->get();
        }

        return view('public.my-orders', compact('orders', 'phone', 'orderNumber', 'attempted'));
    }
}
