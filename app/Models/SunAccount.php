<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Sun ledger account code (§8): linked to students for ranges of financial years (T3), or the
 * Student Control account (§6, T7), or marked "not an ISMS student". Which student a year's lines
 * belong to is SunLinks::studentFor().
 */
class SunAccount extends Model
{
    protected $fillable = ['code', 'is_control', 'not_isms_student', 'name_as_printed', 'first_seen_year'];

    protected function casts(): array
    {
        return ['is_control' => 'boolean', 'not_isms_student' => 'boolean'];
    }

    public function links()
    {
        return $this->hasMany(SunAccountLink::class);
    }

    public function reviews()
    {
        return $this->hasMany(SunAccountReview::class);
    }

    public function lines()
    {
        return $this->hasMany(SunLedgerLine::class);
    }

    public function balances()
    {
        return $this->hasMany(SunAccountBalance::class);
    }

    /** The open link (no end year, not voided), if any. */
    public function openLink(): ?SunAccountLink
    {
        return $this->links()->whereNull('to_fiscal_year')->whereNull('voided_at')->first();
    }
}
