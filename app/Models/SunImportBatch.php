<?php

namespace App\Models;

use App\Services\Sun\SunPeriods;
use Illuminate\Database\Eloquent\Model;

/** One imported Sun Accounts "Account Listing" file (sdp_doc/YASIS_Sun_Finance_Import.md §9, gitignored). */
class SunImportBatch extends Model
{
    protected $fillable = [
        'uploaded_by', 'source_file', 'file_sha256', 'stored_path', 'fiscal_year', 'period_from', 'period_to',
        'account_code_from', 'account_code_to', 'printed_at', 'accounts_count', 'lines_count', 'new_lines',
        'updated_lines', 'missing_lines', 'matched_accounts', 'unmatched_accounts', 'new_accounts', 'warnings',
        'control_balance', 'published_at', 'published_by', 'reverted_at', 'reverted_by',
    ];

    protected function casts(): array
    {
        return ['warnings' => 'array', 'control_balance' => 'decimal:2', 'published_at' => 'datetime', 'reverted_at' => 'datetime'];
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function scopeCurrent($query)
    {
        return $query->whereNull('reverted_at');
    }

    public function yearLabel(): string
    {
        return SunPeriods::label($this->fiscal_year);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }
}
