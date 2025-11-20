<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    // Allow mass assignment for the 'name'
    protected $fillable = ['name'];

    // A role belongs to many users
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}