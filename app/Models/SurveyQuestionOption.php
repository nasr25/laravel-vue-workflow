<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyQuestionOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_question_id',
        'option_text',
        'option_text_ar',
        'option_value',
        'order',
    ];

    public function question()
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}
