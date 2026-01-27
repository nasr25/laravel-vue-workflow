<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'from_department_id',
        'to_department_id',
        'from_user_id',
        'to_user_id',
        'actioned_by',
        'action',
        'from_status',
        'to_status',
        'comments_ar',
        'comments_en',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    public function fromDepartment()
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment()
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function actionedBy()
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }
}
