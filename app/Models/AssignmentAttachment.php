<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'file_path',
        'file_type',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }
}
