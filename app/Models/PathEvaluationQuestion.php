<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PathEvaluationQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_path_id',
        'question',
        'order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function workflowPath()
    {
        return $this->belongsTo(WorkflowPath::class);
    }

    public function evaluations()
    {
        return $this->hasMany(PathEvaluation::class);
    }
}
