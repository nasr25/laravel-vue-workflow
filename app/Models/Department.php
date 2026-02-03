<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_department_a',
        'is_active',
    ];

    protected $casts = [
        'is_department_a' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'department_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function managers()
    {
        return $this->belongsToMany(User::class, 'department_user')
            ->where('department_user.role', 'manager')
            ->withTimestamps();
    }

    public function employees()
    {
        return $this->belongsToMany(User::class, 'department_user')
            ->where('department_user.role', 'employee')
            ->withTimestamps();
    }

    public function workflowPathSteps()
    {
        return $this->hasMany(WorkflowPathStep::class);
    }

    public function currentRequests()
    {
        return $this->hasMany(Request::class, 'current_department_id');
    }


    public function requests()
    {
        return $this->hasMany(Request::class);
    }

}
