<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A ledger line category (§5), checked in `position` order; `restricted_by_default` hides it from families (T4). */
class FeeCategory extends Model
{
    protected $fillable = ['key', 'label', 'position', 'restricted_by_default', 'pending_question'];

    protected function casts(): array
    {
        return ['restricted_by_default' => 'boolean'];
    }

    public function rules()
    {
        return $this->hasMany(FeeCategoryRule::class);
    }
}
