<?php

namespace App\Console\Commands;

use App\Models\ServiceFile;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneServiceFiles extends Command
{
    protected $signature = 'younz:prune-service-files {--dry-run}';

    protected $description = 'Remove expired private files from completed or cancelled service orders';

    public function handle(AuditLogger $audit): int
    {
        $count = 0;
        ServiceFile::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereHas('order', fn ($query) => $query->whereIn('status', ['selesai', 'dibatalkan']))
            ->chunkById(100, function ($files) use (&$count, $audit): void {
                foreach ($files as $file) {
                    $count++;
                    if ($this->option('dry-run')) {
                        continue;
                    }
                    $audit->log('service_file.expired', $file, before: $file->toArray());
                    Storage::disk($file->disk)->delete($file->path);
                    $file->delete();
                }
            });

        $this->info(($this->option('dry-run') ? 'File yang akan dihapus: ' : 'File kedaluwarsa dihapus: ').$count);

        return self::SUCCESS;
    }
}
