<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What a batch did to a line or balance: created it (previous null) or changed it (the previous values). */
class SunLedgerVersion extends Model
{
    protected $fillable = ['batch_id', 'record_type', 'record_id', 'previous', 'reverted_at'];

    protected function casts(): array
    {
        return ['previous' => 'array', 'reverted_at' => 'datetime'];
    }
}
