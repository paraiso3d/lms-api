<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscussionAttachment extends Model
{
    use HasFactory;
    public function discussion()
    {
        return $this->belongsTo(Discussion::class, 'discussion_id');
    }
}
