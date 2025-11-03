<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'icon',
        'has_file_upload',
        'file_types_allowed',
        'max_file_size_mb',
        'is_active'
    ];

    protected $casts = [
        'has_file_upload' => 'boolean',
        'is_active' => 'boolean',
        'file_types_allowed' => 'array',
        'max_file_size_mb' => 'integer',
    ];

    /**
     * Get the workflow templates for this form type
     */
    public function workflowTemplates()
    {
        return $this->hasMany(WorkflowTemplate::class);
    }

    /**
     * Get the active workflow template for this form type
     */
    public function activeWorkflowTemplate()
    {
        return $this->hasOne(WorkflowTemplate::class)->where('is_active', true);
    }

    /**
     * Get ideas submitted using this form type
     */
    public function ideas()
    {
        return $this->hasMany(Idea::class);
    }

    /**
     * Scope to get only active form types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
