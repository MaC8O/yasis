<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A ledger account code linked to a student for a range of financial years (T3: a code can be
 * reused after a student leaves). Open while to_fiscal_year is null; a voided link (a mistake)
 * covers nothing and is kept.
 */
class SunAccountLink extends Model
{
    protected $fillable = [
        'sun_account_id', 'student_id', 'from_fiscal_year', 'to_fiscal_year', 'accepted_name',
        'linked_by', 'linked_at', 'ended_by', 'ended_at', 'voided_by', 'voided_at',
    ];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime', 'ended_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(SunAccount::class, 'sun_account_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('voided_at');
    }

    public function scopeCovering($query, int $fiscalYear)
    {
        return $query->whereNull('voided_at')->where('from_fiscal_year', '<=', $fiscalYear)
            ->where(fn ($q) => $q->whereNull('to_fiscal_year')->orWhere('to_fiscal_year', '>=', $fiscalYear));
    }

    public function isOpen(): bool
    {
        return $this->to_fiscal_year === null && $this->voided_at === null;
    }

    public function covers(int $fiscalYear): bool
    {
        return $this->voided_at === null && $this->from_fiscal_year <= $fiscalYear
            && ($this->to_fiscal_year === null || $this->to_fiscal_year >= $fiscalYear);
    }
}
