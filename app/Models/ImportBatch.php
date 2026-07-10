<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = ['uploaded_by', 'period', 'source_file', 'row_count', 'uploaded_at', 'published_at'];

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function uploadedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'uploaded_by');
    }

    public function importedFeeRecords()
    {
        return $this->hasMany(ImportedFeeRecord::class);
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->published_at !== null;
    }
}
