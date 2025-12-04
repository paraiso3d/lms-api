<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscussionReply extends Model
{
    protected $fillable = [
        'discussion_id',
        'user_id',
        'reply',
        'is_archived',
    ];

    // Reply belongs to a discussion
    public function discussion()
    {
        return $this->belongsTo(Discussion::class, 'discussion_id');
    }

    // Reply belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
