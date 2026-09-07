<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueHealth extends Command
{
    protected $signature = 'queue:health';

    protected $description = 'Show safe queue counts and visibility settings without message payloads.';

    public function handle(): int
    {
        $driver = config('queue.default');
        $retry = (int) config("queue.connections.{$driver}.retry_after", 0);
        $this->line('Connection: '.$driver);
        $this->line('Visibility seconds: '.$retry);
        if ($driver === 'database') {
            $connection = DB::connection(config('queue.connections.database.connection'));
            $jobs = $connection->table(config('queue.connections.database.table', 'jobs'));
            $this->line('Pending: '.(clone $jobs)->count());
            $oldest = (clone $jobs)->min('created_at');
            $this->line('Oldest age seconds: '.($oldest ? max(0, time() - (int) $oldest) : 0));
            $this->line('Reserved: '.(clone $jobs)->whereNotNull('reserved_at')->count());
        }
        $failedConnection = DB::connection(config('queue.failed.database'));
        $failedTable = config('queue.failed.table', 'failed_jobs');
        if ($failedConnection->getSchemaBuilder()->hasTable($failedTable)) {
            $this->line('Failed: '.$failedConnection->table($failedTable)->count());
        }
        if (DB::getSchemaBuilder()->hasTable('topup_fulfillment_outbox')) {
            $this->line('Unpublished fulfillment intents: '.DB::table('topup_fulfillment_outbox')->whereNull('published_at')->count());
        }
        if (DB::getSchemaBuilder()->hasTable('topup_orders')) {
            $this->line('Stale paid fulfillment: '.DB::table('topup_orders')
                ->where('payment_status', 'paid')->whereIn('fulfillment_status', ['queued', 'processing', 'provider_pending'])
                ->where('updated_at', '<', now()->subMinutes(10))->count());
        }
        if ($driver === 'sync') {
            $this->error('Sync executes jobs inside the request and is not a supervised background queue.');
            return self::FAILURE;
        }
        if (in_array($driver, ['database', 'redis', 'beanstalkd'], true) && $retry < 300) {
            $this->error('Visibility must be at least 300 seconds for current long-running jobs. Check effective configuration.');
            return self::FAILURE;
        }
        $this->warn('Counts alone do not prove worker progress. Compare successive readings and service logs.');
        return self::SUCCESS;
    }
}
