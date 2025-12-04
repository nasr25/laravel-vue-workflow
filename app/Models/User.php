<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    // Specify the guard for Spatie permissions
    protected $guard_name = 'sanctum';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function submittedRequests()
    {
        return $this->hasMany(Request::class, 'user_id');
    }

    public function assignedRequests()
    {
        return $this->hasMany(Request::class, 'current_user_id');
    }

    // Helper methods
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isManagerOf($departmentId)
    {
        return $this->departments()
            ->where('departments.id', $departmentId)
            ->wherePivot('role', 'manager')
            ->exists();
    }

    public function isEmployeeOf($departmentId)
    {
        return $this->departments()
            ->where('departments.id', $departmentId)
            ->exists();
    }
}
