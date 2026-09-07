<?php

namespace App\Ai;

use RuntimeException;

final class VisionImage
{
    private function __construct(private readonly string $bytes, private readonly string $mime) {}

    public static function fromBytes(string $bytes): self
    {
        if ($bytes === '' || strlen($bytes) > 5 * 1024 * 1024) {
            throw new RuntimeException('Gambar harus berukuran maksimal 5 MiB.');
        }
        $info = @getimagesizefromstring($bytes);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! is_array($info) || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || ($info['mime'] ?? null) !== $mime || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 20000000) {
            throw new RuntimeException('Gunakan JPEG, PNG, atau WebP valid maksimal 20 megapiksel.');
        }
        if (! function_exists('imagecreatefromstring') || ! ($decoded = @imagecreatefromstring($bytes))) {
            throw new RuntimeException('Gambar tidak dapat dibaca oleh server.');
        }
        imagedestroy($decoded);

        return new self($bytes, $mime);
    }

    public function content(): array
    {
        return ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$this->mime.';base64,'.base64_encode($this->bytes)]];
    }

    public function __debugInfo(): array
    {
        return ['mime' => $this->mime];
    }
}
