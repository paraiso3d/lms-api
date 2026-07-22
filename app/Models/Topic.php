<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use HasFactory;
    protected $fillable = [
        'topic_name',
        'is_archived',
    ];
    public function classes()
    {
        return $this->belongsToMany(
            Classess::class,
            'class_topic',
            'topic_id',
            'class_id'
        );
    }
}
