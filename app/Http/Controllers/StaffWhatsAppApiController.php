<?php

namespace App\Http\Controllers;

use App\Integrations\WhatsApp\WhatsAppGatewayClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Throwable;

class StaffWhatsAppApiController extends Controller
{
    public function __construct(private readonly WhatsAppGatewayClient $gateway) {}

    public function status(): JsonResponse
    {
        $this->authorizeStaff();

        try {
            return response()->json(['data' => $this->gateway->status()]);
        } catch (ConnectionException) {
            return response()->json(['data' => [
                'state' => 'unavailable',
                'connected' => false,
                'qr' => null,
                'account' => null,
                'lastError' => 'Gateway WhatsApp lokal belum berjalan di port 3000.',
            ]]);
        } catch (Throwable) {
            return response()->json(['data' => [
                'state' => 'error',
                'connected' => false,
                'qr' => null,
                'account' => null,
                'lastError' => 'Status gateway WhatsApp tidak dapat dimuat.',
            ]]);
        }
    }

    public function reconnect(): JsonResponse
    {
        $this->authorizeStaff();
        $this->gateway->reconnect();

        return response()->json(['message' => 'Gateway WhatsApp sedang dihubungkan ulang.']);
    }

    public function logout(): JsonResponse
    {
        $this->authorizeStaff();
        $this->gateway->logout();

        return response()->json(['message' => 'Perangkat WhatsApp berhasil dikeluarkan.']);
    }

    private function authorizeStaff(): void
    {
        $user = request()->user();
        abort_unless($user?->isStaff() && $user->tokenCan('staff:dashboard'), 403);
    }
}
