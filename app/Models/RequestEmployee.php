<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestEmployee extends Model
{
    protected $fillable = [
        'request_id',
        'employee_name',
        'employee_email',
        'employee_department',
        'employee_title',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class);
    }
}
