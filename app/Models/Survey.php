<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'title_ar',
        'description',
        'description_ar',
        'is_active',
        'trigger_point',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function questions()
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('order');
    }

    public function responses()
    {
        return $this->hasMany(SurveyResponse::class);
    }
}
