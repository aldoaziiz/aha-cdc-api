<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Status;
use App\Models\Role;

class Guardian extends Model
{
    protected $fillable = [
        'name',
        'id_number',
        'address',
        'phone',
        'status_id'
    ];

    public function children()
    {
        return $this->hasMany(Child::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
