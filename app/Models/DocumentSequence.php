<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sequence_date' => 'date', 'last_number' => 'integer'];
    }
}
