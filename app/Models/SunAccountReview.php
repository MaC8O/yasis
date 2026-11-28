<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * T3: a linked code printed under a different name, held until the Treasurer says whether it is
 * the same student (a rename) or a different one. While pending, the account's lines for that
 * year match nobody.
 */
class SunAccountReview extends Model
{
    public const PENDING = 'pending';

    public const SAME = 'same';

    public const RELINKED = 'relinked';

    protected $fillable = ['sun_account_id', 'fiscal_year', 'previous_name', 'printed_name', 'status', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(SunAccount::class, 'sun_account_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::PENDING);
    }
}
