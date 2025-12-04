<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $fillable = [
        'class_id',
        'title',
        'description',
        'total_points',
        'time_limit',
        'due_date',
        'is_archived',
        'created_by',
    ];

    // A quiz belongs to a class
    public function class()
    {
        return $this->belongsTo(Classess::class, 'class_id');
    }

    // A quiz has many questions
    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)
            ->where('is_archived', 0);
    }

    // Submissions by students
    public function submissions()
    {
        return $this->hasMany(QuizSubmission::class)
            ->where('is_archived', 0);
    }
}
