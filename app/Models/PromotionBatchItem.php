<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionBatchItem extends Model
{
    protected $fillable = ['promotion_batch_id', 'student_id', 'action', 'to_section_id'];

    public function batch()
    {
        return $this->belongsTo(PromotionBatch::class, 'promotion_batch_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function toSection()
    {
        return $this->belongsTo(Section::class, 'to_section_id');
    }
}
