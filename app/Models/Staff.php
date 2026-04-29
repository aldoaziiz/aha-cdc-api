<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Role;
use App\Models\Status;

class Staff extends Model
{
    protected $fillable = [
        'name',
        'title',
        'email',
        'phone',
        'address',
        'staff_role_id',
        'status_id'
    ];

    public function role()
    {
        return $this->belongsTo(StaffRole::class, 'staff_role_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }
}
