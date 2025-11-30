<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdeaApproval extends Model
{
    protected $fillable = [
        'idea_id',
        'department_id',
        'approver_id',
        'approver_type',
        'workflow_step_id',
        'step',
        'status',
        'approvals_received',
        'approvals_required',
        'comments',
        'reviewed_at',
        'arrived_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'arrived_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'step' => 'integer',
        'approvals_received' => 'integer',
        'approvals_required' => 'integer',
    ];

    /**
     * Get the idea this approval belongs to
     */
    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    /**
     * Get the department for this approval
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the workflow step for this approval
     */
    public function workflowStep()
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    /**
     * Get the approver (manager or employee) who reviewed this approval
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Legacy: Get the manager who reviewed this approval (for backward compatibility)
     */
    public function manager()
    {
        return $this->approver();
    }

    /**
     * Check if this approval is from an employee
     */
    public function isEmployeeApproval()
    {
        return $this->approver_type === 'employee';
    }

    /**
     * Check if this approval is from a manager
     */
    public function isManagerApproval()
    {
        return $this->approver_type === 'manager';
    }

    /**
     * Check if this step requires more approvals
     */
    public function needsMoreApprovals()
    {
        return $this->approvals_received < $this->approvals_required;
    }

    /**
     * Check if this step has received all required approvals
     */
    public function hasAllApprovals()
    {
        return $this->approvals_received >= $this->approvals_required;
    }
}
