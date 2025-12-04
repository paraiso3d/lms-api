<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discussion extends Model
{
    protected $fillable = [
        'class_id',
        'user_id',
        'title',
        'description',
        'is_archived',
    ];

    // Discussion belongs to a class
    public function class()
    {
        return $this->belongsTo(Classess, 'class_id');
    }

    // Discussion belongs to a user (author)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Discussion has many replies
    public function replies()
    {
        return $this->hasMany(DiscussionReply::class)
            ->where('is_archived', 0)
            ->orderBy('created_at', 'asc');
    }
}
