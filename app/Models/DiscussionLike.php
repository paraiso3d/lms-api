<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscussionLike extends Model
{
    protected $fillable = [
        'discussion_id',
        'user_id',
    ];

    public function discussion()
    {
        return $this->belongsTo(Discussion::class, 'discussion_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
