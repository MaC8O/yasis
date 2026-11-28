<?php

namespace App\Models;

use App\Support\LedgerFormat;
use Illuminate\Database\Eloquent\Model;

/**
 * What families see of one account's ledger for one financial year, as built when an import was
 * published (SunFamilyStatements). content: opening, months of visible lines, the hand-hidden
 * lines' total as "adjustments", closing, and the hidden lines themselves for staff allowed to see
 * them (never rendered to families).
 */
class SunFamilyStatement extends Model
{
    protected $fillable = ['sun_account_id', 'student_id', 'fiscal_year', 'batch_id', 'content', 'closing_balance'];

    protected function casts(): array
    {
        return ['content' => 'array', 'closing_balance' => 'decimal:2'];
    }

    public function account()
    {
        return $this->belongsTo(SunAccount::class, 'sun_account_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function batch()
    {
        return $this->belongsTo(SunImportBatch::class, 'batch_id');
    }

    public function status(): string
    {
        return LedgerFormat::status((string) $this->closing_balance);
    }
}
