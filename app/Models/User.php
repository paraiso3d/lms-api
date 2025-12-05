<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role_id',
        'avatar',
        'is_archived',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    // AUTO HASH PASSWORD
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = bcrypt($value);
        }
    }

    // Relationship to Role
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function classesTeaching()
    {
        return $this->hasMany(Classess::class, 'teacher_id');
    }

    public function classesEnrolled()
    {
        return $this->belongsToMany(Classess::class, 'class_student', 'student_id', 'class_id');
    }
}
