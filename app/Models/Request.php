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
        'rejection_reason',
        'additional_details',
        'submitted_at',
        'completed_at',
        'expected_execution_date',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'expected_execution_date' => 'date',
    ];

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
