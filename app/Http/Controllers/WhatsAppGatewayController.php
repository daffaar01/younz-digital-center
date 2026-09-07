<?php

namespace App\Http\Controllers;

use App\Integrations\WhatsApp\WhatsAppGatewayClient;
use App\Support\AuditLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class WhatsAppGatewayController extends Controller
{
    public function __construct(
        private readonly WhatsAppGatewayClient $gateway,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        try {
            $gateway = $this->gateway->status();
        } catch (ConnectionException) {
            $gateway = ['state' => 'unavailable', 'connected' => false, 'qr' => null, 'account' => null, 'lastError' => 'Service WhatsApp tidak dapat dijangkau.'];
        } catch (Throwable $error) {
            report($error);
            $gateway = ['state' => 'error', 'connected' => false, 'qr' => null, 'account' => null, 'lastError' => 'Status WhatsApp tidak dapat dimuat.'];
        }

        return view('whatsapp.index', compact('gateway'));
    }

    public function reconnect(): RedirectResponse
    {
        $this->gateway->reconnect();
        $this->audit->log('whatsapp_gateway.reconnected');

        return back()->with('status', 'Gateway WhatsApp sedang dihubungkan ulang.');
    }

    public function logout(): RedirectResponse
    {
        $this->gateway->logout();
        $this->audit->log('whatsapp_gateway.logged_out');

        return back()->with('status', 'Perangkat WhatsApp berhasil dikeluarkan.');
    }
}
