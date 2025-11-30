<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    protected $fillable = [
        'workflow_template_id',
        'step_order',
        'step_name',
        'approver_type',
        'department_id',
        'required_approvals_count',
        'approval_mode',
        'can_skip',
        'timeout_hours'
    ];

    protected $casts = [
        'step_order' => 'integer',
        'required_approvals_count' => 'integer',
        'can_skip' => 'boolean',
        'timeout_hours' => 'integer',
    ];

    /**
     * Get the workflow template this step belongs to
     */
    public function workflowTemplate()
    {
        return $this->belongsTo(WorkflowTemplate::class);
    }

    /**
     * Get the department for this step
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the assigned approvers for this step
     */
    public function approvers()
    {
        return $this->hasMany(WorkflowStepApprover::class);
    }

    /**
     * Get the users assigned as approvers via the pivot
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'workflow_step_approvers')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get idea approvals for this step
     */
    public function ideaApprovals()
    {
        return $this->hasMany(IdeaApproval::class);
    }

    /**
     * Check if this step requires employee approvals
     */
    public function requiresEmployeeApproval()
    {
        return in_array($this->approver_type, ['employee', 'either']);
    }

    /**
     * Check if this step requires manager approval
     */
    public function requiresManagerApproval()
    {
        return in_array($this->approver_type, ['manager', 'either']);
    }

    /**
     * Check if all approvals are required or just a count
     */
    public function requiresAllApprovals()
    {
        return $this->approval_mode === 'all';
    }
}
