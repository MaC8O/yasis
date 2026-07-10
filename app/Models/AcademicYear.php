<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $fillable = ['year_label', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function terms()
    {
        return $this->hasMany(Term::class);
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }
}
