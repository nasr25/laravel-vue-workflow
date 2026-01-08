<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Request extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'idea_type_id',
        'department_id',
        'benefits',
        'user_id',
        'current_department_id',
        'current_user_id',
        'workflow_path_id',
        'status',
        'progress_percentage',
        'idea_type',
        'rejection_reason',
        'additional_details',
        'submitted_at',
        'completed_at',
        'expected_execution_date',
        'current_stage_started_at',
        'sla_reminder_sent_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'expected_execution_date' => 'date',
        'current_stage_started_at' => 'datetime',
        'sla_reminder_sent_at' => 'datetime',
    ];

    // Add idea_ownership to the JSON output (since idea_type is overwritten by the ideaType relationship)
    protected $appends = ['idea_ownership'];

    // Accessor to expose the idea_type column as idea_ownership
    public function getIdeaOwnershipAttribute()
    {
        return $this->attributes['idea_type'] ?? 'individual';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ideaType()
    {
        return $this->belongsTo(IdeaType::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function currentDepartment()
    {
        return $this->belongsTo(Department::class, 'current_department_id');
    }

    public function currentAssignee()
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    public function workflowPath()
    {
        return $this->belongsTo(WorkflowPath::class);
    }

    public function attachments()
    {
        return $this->hasMany(RequestAttachment::class);
    }

    public function transitions()
    {
        return $this->hasMany(RequestTransition::class)->orderBy('created_at', 'desc');
    }

    public function evaluations()
    {
        return $this->hasMany(RequestEvaluation::class);
    }

    public function pathEvaluations()
    {
        return $this->hasMany(PathEvaluation::class);
    }

    public function employees()
    {
        return $this->hasMany(RequestEmployee::class);
    }

    public function getCurrentStep()
    {
        if (!$this->workflow_path_id || !$this->current_department_id) {
            return null;
        }

        return WorkflowPathStep::where('workflow_path_id', $this->workflow_path_id)
            ->where('department_id', $this->current_department_id)
            ->first();
    }
}
