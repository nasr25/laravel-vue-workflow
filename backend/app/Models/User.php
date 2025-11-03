<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the role of the user
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole($roleName)
    {
        return $this->role && $this->role->name === $roleName;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is manager
     */
    public function isManager()
    {
        return $this->hasRole('manager');
    }

    /**
     * Check if user is employee
     */
    public function isEmployee()
    {
        return $this->hasRole('employee');
    }

    /**
     * Get departments where user is a manager
     */
    public function managedDepartments()
    {
        return $this->belongsToMany(Department::class, 'department_managers');
    }

    /**
     * Get departments where user is an employee
     */
    public function employeeDepartments()
    {
        return $this->belongsToMany(Department::class, 'department_employees')
            ->withPivot('permission')
            ->withTimestamps();
    }

    /**
     * Get department employee records for this user
     */
    public function departmentEmployeeRecords()
    {
        return $this->hasMany(DepartmentEmployee::class);
    }

    /**
     * Get workflow steps where user is assigned as approver
     */
    public function workflowStepApprovers()
    {
        return $this->hasMany(WorkflowStepApprover::class);
    }

    /**
     * Get workflow steps where user can approve
     */
    public function assignedWorkflowSteps()
    {
        return $this->belongsToMany(WorkflowStep::class, 'workflow_step_approvers')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get ideas submitted by user
     */
    public function ideas()
    {
        return $this->hasMany(Idea::class);
    }

    /**
     * Get approvals made by this user (as manager or employee)
     */
    public function approvalsMade()
    {
        return $this->hasMany(IdeaApproval::class, 'approver_id');
    }
}
