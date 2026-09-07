<?php

namespace App\Ai;

class OrderIntakeParser
{
    /** @return array<string, mixed> */
    public function parse(string $message): array
    {
        $text = str($message)->lower()->squish()->toString();
        $service = collect(['fotokopi', 'print', 'scan', 'ketik', 'desain', 'website', 'aplikasi'])
            ->first(fn (string $candidate) => str_contains($text, $candidate));

        preg_match('/(\d+)\s*(?:rangkap|copy|salinan)/i', $text, $copies);
        preg_match('/\b(satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan|sepuluh)\s*(?:rangkap|copy|salinan)\b/i', $text, $wordCopies);
        preg_match('/\b(a3|a4|a5|f4|letter|legal)\b/i', $text, $paper);
        $numberWords = ['satu' => 1, 'dua' => 2, 'tiga' => 3, 'empat' => 4, 'lima' => 5, 'enam' => 6, 'tujuh' => 7, 'delapan' => 8, 'sembilan' => 9, 'sepuluh' => 10];

        $data = [
            'service' => $service,
            'paper_size' => isset($paper[1]) ? strtoupper($paper[1]) : null,
            'color_mode' => str_contains($text, 'hitam putih') || str_contains($text, 'bw') ? 'black_white' : (str_contains($text, 'warna') ? 'color' : null),
            'sides' => str_contains($text, 'bolak-balik') || str_contains($text, 'duplex') ? 'duplex' : (str_contains($text, 'satu sisi') ? 'simplex' : null),
            'copies' => isset($copies[1]) ? (int) $copies[1] : (isset($wordCopies[1]) ? $numberWords[$wordCopies[1]] : null),
            'finishing' => collect(['jilid', 'staples', 'laminasi', 'potong'])->first(fn (string $value) => str_contains($text, $value)),
            'pickup_time' => collect(['pagi', 'siang', 'sore', 'malam'])->first(fn (string $value) => str_contains($text, $value)),
            'notes' => $message,
        ];

        $required = ['service', 'copies'];
        $data['uncertain_fields'] = collect($required)->filter(fn (string $key) => empty($data[$key]))->values()->all();
        $data['requires_follow_up'] = $data['uncertain_fields'] !== [];

        return $data;
    }
}
