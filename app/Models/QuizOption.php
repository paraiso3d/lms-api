<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizOption extends Model
{
    protected $fillable = [
        'question_id',
        'option_text',
        'is_correct',
        'is_archived',
    ];

    public function question()
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }
}
