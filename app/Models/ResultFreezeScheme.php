<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A copy of one grading scheme's settings and band sets, as used by a freeze. */
class ResultFreezeScheme extends Model
{
    protected $fillable = ['result_freeze_id', 'grading_scheme_id', 'settings', 'mark_bands', 'result_bands'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'mark_bands' => 'array', 'result_bands' => 'array'];
    }

    public function freeze()
    {
        return $this->belongsTo(ResultFreeze::class, 'result_freeze_id');
    }
}
