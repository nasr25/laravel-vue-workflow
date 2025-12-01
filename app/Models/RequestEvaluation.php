<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'evaluation_question_id',
        'evaluated_by',
        'score',
        'notes',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    public function question()
    {
        return $this->belongsTo(EvaluationQuestion::class, 'evaluation_question_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function evaluatedBy()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
