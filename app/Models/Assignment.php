<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'title',
        'instructions',
        'max_points',
        'due_date',
        'topic',
        'attachment_count',
        'is_archived',
        'created_by',
    ];

    public function class()
    {
        return $this->belongsTo(Classess::class, 'class_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(AssignmentAttachment::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }
}
