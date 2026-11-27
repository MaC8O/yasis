<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A memo keyword (case-insensitive, anywhere in the memo), or "any CR journal credit", for a category. */
class FeeCategoryRule extends Model
{
    protected $fillable = ['fee_category_id', 'keyword', 'cr_credit', 'position', 'active'];

    protected function casts(): array
    {
        return ['cr_credit' => 'boolean', 'active' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }
}
