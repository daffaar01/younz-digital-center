<?php

namespace App\Support;

use App\Models\TopupOrder;

class TopupReceiptPdf
{
    public function render(TopupOrder $order): string
    {
        $offline = str_starts_with((string) $order->source_reference, 'offline:');
        $whatsapp = str_starts_with((string) $order->source_reference, 'whatsapp:');
        $payment = $offline ? 'TUNAI' : strtoupper(str_replace('_', ' ', $order->midtrans_payment_type ?: 'MIDTRANS'));
        $source = $offline ? 'Transaksi toko' : ($whatsapp ? 'WhatsApp' : 'Website');
        $lines = [
            'YOUNZ DIGITAL CENTER',
            (string) config('services.store.address'),
            'WhatsApp '.config('services.whatsapp.display_number'),
            '',
            'STRUK PEMBAYARAN',
            strtoupper($order->payment_status->label()),
            '',
            'No. transaksi: '.$order->order_number,
            'Tanggal bayar: '.($order->paid_at ?? $order->created_at)?->format('d/m/Y H:i:s').' WIB',
            'Kanal: '.$source,
            'Pembayaran: '.$payment,
        ];

        if (! $offline && $order->midtrans_transaction_id) {
            $lines[] = 'Ref. pembayaran: '.$order->midtrans_transaction_id;
        }

        $lines = [
            ...$lines,
            '',
            (string) $order->product_name,
            'Jenis: '.$order->transaction_type->label(),
            'SKU: '.$order->sku,
            'Tujuan: '.$order->maskedDestination(),
            'Pemesan: '.$order->customer_name,
        ];

        if ($order->provider_customer_name) {
            $lines[] = 'Nama pelanggan: '.$order->provider_customer_name;
        }

        $lines = [
            ...$lines,
            'Status layanan: '.$order->fulfillment_status->label(),
            '',
            'Subtotal: Rp '.number_format($order->selling_price, 0, ',', '.'),
        ];

        if ($order->admin_fee > 0) {
            $lines[] = 'Biaya admin: Rp '.number_format($order->admin_fee, 0, ',', '.');
        }

        $lines[] = 'TOTAL: Rp '.number_format($order->total_amount, 0, ',', '.');

        if ($order->serial_number) {
            $lines = [
                ...$lines,
                '',
                'NOMOR SERI / TOKEN',
                (string) $order->serial_number,
            ];
        }

        if ($order->digiflazz_reference) {
            $lines[] = 'Ref. provider: '.$order->digiflazz_reference;
        }

        if ($order->provider_rc) {
            $lines[] = 'Kode provider: '.$order->provider_rc;
        }

        $wrapped = [];
        foreach ($lines as $line) {
            $wrapped = [...$wrapped, ...$this->wrap($line)];
        }

        return $this->document($wrapped);
    }

    /** @return list<string> */
    private function wrap(string $value): array
    {
        $value = $this->ascii(trim($value));

        return $value === '' ? [''] : explode("\n", wordwrap($value, 40, "\n", true));
    }

    /** @param list<string> $lines */
    private function document(array $lines): string
    {
        $height = max(200, 80 + count($lines) * 14);
        $commands = [
            'q',
            'BT',
            '/F1 8 Tf',
            '16 '.($height - 50).' Td',
        ];

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $commands[] = '0 -14 Td';
            }

            $commands[] = '('.$this->escape($this->ascii($line)).') Tj';
        }

        $commands[] = 'ET';
        $commands[] = 'Q';
        $stream = implode("\n", $commands)."\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 226 '.$height.'] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
            '<< /Length '.strlen($stream).">>\nstream\n".$stream.'endstream',
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($index = 1; $index <= count($objects); $index++) {
            $pdf .= sprintf('%010d 00000 n %s', $offsets[$index], "\n");
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    private function ascii(string $value): string
    {
        if (function_exists('iconv')) {
            return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        }

        return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
