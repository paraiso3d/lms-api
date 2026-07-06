<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscussionAttachment extends Model
{
    use HasFactory;
    protected $fillable = [
        'discussion_id',
        'file_name',
        'file_path',
    ];
    public function discussion()
    {
        return $this->belongsTo(Discussion::class, 'discussion_id');
    }
}
