<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    protected $fillable = [
        'submission_id',
        'question_id',
        'selected_option_id',
        'is_correct',
    ];

    public function submission()
    {
        return $this->belongsTo(QuizSubmission::class, 'submission_id');
    }

    public function question()
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }

    public function selectedOption()
    {
        return $this->belongsTo(QuizOption::class, 'selected_option_id');
    }
}
