<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One trait of a student's personality record for a term, rated A, B or C by the homeroom teacher (§7.1). */
class PersonalityRating extends Model
{
    /** The five traits, in the card's order. */
    public const TRAITS = [
        'creative_thinking' => 'CREATIVE THINKING',
        'character' => 'CHARACTER',
        'discipline' => 'DISCIPLINE',
        'social' => 'SOCIAL',
        'responsibility' => 'RESPONSIBILITY',
    ];

    public const RATINGS = ['A', 'B', 'C'];

    protected $fillable = ['student_id', 'term_id', 'trait', 'rating', 'rated_by'];

    public function term()
    {
        return $this->belongsTo(Term::class);
    }
}
