<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteDigitalProductImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly string $disk,
        public readonly string $path,
    ) {}

    public function handle(): void
    {
        $storage = Storage::disk($this->disk);
        if (! $storage->exists($this->path)) {
            return;
        }

        if (! $storage->delete($this->path) || $storage->exists($this->path)) {
            throw new RuntimeException("Gagal membersihkan foto Produk Digital pada disk {$this->disk}.");
        }
    }
}
