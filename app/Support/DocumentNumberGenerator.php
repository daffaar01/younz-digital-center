<?php

namespace App\Support;

use App\Models\DocumentSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentNumberGenerator
{
    public function next(string $type, ?Carbon $date = null): string
    {
        $date ??= now();
        $sequenceDate = $date->toDateString();

        return DB::transaction(function () use ($type, $date, $sequenceDate): string {
            DocumentSequence::query()->upsert([
                'type' => $type, 'sequence_date' => $sequenceDate, 'last_number' => 0,
            ], ['type', 'sequence_date'], ['type']);
            $sequence = DocumentSequence::query()->where('type', $type)
                ->whereDate('sequence_date', $sequenceDate)->lockForUpdate()->firstOrFail();
            $sequence->increment('last_number');

            return sprintf('%s-%s-%04d', $type, $date->format('Ymd'), $sequence->fresh()->last_number);
        }, 3);
    }
}
