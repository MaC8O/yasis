<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An account's opening and closing balance for one financial year (§9). */
class SunAccountBalance extends Model
{
    protected $fillable = [
        'sun_account_id', 'fiscal_year', 'opening_balance', 'closing_balance', 'account_name_as_printed', 'is_control',
        'created_batch_id', 'last_batch_id', 'removed_at',
    ];

    public const VERSIONED = ['opening_balance', 'closing_balance', 'account_name_as_printed', 'last_batch_id', 'removed_at'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'closing_balance' => 'decimal:2', 'is_control' => 'boolean', 'removed_at' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(SunAccount::class, 'sun_account_id');
    }

    public function scopeCurrent($query)
    {
        return $query->whereNull('removed_at');
    }

    /** Owing (above 0), Settled (0) or In credit (below 0), from the closing balance (§9, T6). */
    public function status(): string
    {
        return match ((float) $this->closing_balance <=> 0.0) {
            1 => 'Owing',
            0 => 'Settled',
            default => 'In credit',
        };
    }
}
