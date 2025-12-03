<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classess extends Model
{
    protected $table = 'classes';

    // Mass assignable fields
    protected $fillable = [
        'class_name',
        'section',
        'subject',
        'room',
        'class_code',
        'teacher_id',
        'description',
        'is_archived',
    ];

    // Cast is_archived to boolean
    protected $casts = [
        'is_archived' => 'boolean',
    ];

    /**
     * Relationship: the teacher (owner) of the class
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id', 'id');
    }

    /**
     * Relationship: students enrolled in this class (many-to-many)
     * Assuming a pivot table 'class_student' with columns: class_id, student_id
     */
    public function students()
    {
        return $this->belongsToMany(User::class, 'class_student', 'class_id', 'student_id');
    }
}
