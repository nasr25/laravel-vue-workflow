<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTemplate extends Model
{
    protected $fillable = [
        'form_type_id',
        'name',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the form type this workflow belongs to
     */
    public function formType()
    {
        return $this->belongsTo(FormType::class);
    }

    /**
     * Get the workflow steps for this template
     */
    public function steps()
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    /**
     * Get ideas using this workflow template
     */
    public function ideas()
    {
        return $this->hasMany(Idea::class);
    }

    /**
     * Scope to get only active workflows
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the total number of steps in this workflow
     */
    public function getTotalStepsAttribute()
    {
        return $this->steps()->count();
    }
}
