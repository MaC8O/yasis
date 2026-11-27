<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One Sun ledger line (§2, §9): signed amounts, debit positive; never hard-deleted (removed_at). */
class SunLedgerLine extends Model
{
    protected $fillable = [
        'sun_account_id', 'sun_account_code', 'created_batch_id', 'last_batch_id', 'fiscal_year', 'period', 'period_month',
        'txn_date', 'date_suspect', 'journal_type', 'reference', 'journal_no', 'journal_line', 'allocated', 'amount',
        'running_balance', 'memo', 'category', 'category_overridden', 'billed_month', 'is_restricted',
        'restricted_overridden', 'missing_from_latest', 'file_line', 'removed_at',
    ];

    /** The columns a batch may change on an existing line (§10), and a revert restores. */
    public const VERSIONED = [
        'period', 'period_month', 'txn_date', 'date_suspect', 'reference', 'allocated', 'amount', 'running_balance',
        'memo', 'category', 'billed_month', 'is_restricted', 'missing_from_latest', 'last_batch_id', 'removed_at', 'file_line',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date', 'txn_date' => 'date', 'billed_month' => 'date', 'removed_at' => 'datetime',
            'date_suspect' => 'boolean', 'allocated' => 'boolean', 'category_overridden' => 'boolean',
            'is_restricted' => 'boolean', 'restricted_overridden' => 'boolean', 'missing_from_latest' => 'boolean',
            'amount' => 'decimal:2', 'running_balance' => 'decimal:2',
        ];
    }

    public function account()
    {
        return $this->belongsTo(SunAccount::class, 'sun_account_id');
    }

    public function scopeCurrent($query)
    {
        return $query->whereNull('removed_at');
    }
}
