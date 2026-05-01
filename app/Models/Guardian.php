<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Status;
use App\Models\GuardianRole;

class Guardian extends Model
{
    protected $fillable = [
        'name',
        'id_number',
        'address',
        'phone',
        'status_id',
    ];

    protected static function booted()
    {
        static::creating(function ($guardian) {
            if (!$guardian->status_id) {
                $guardian->status_id = 1;
            }
        });
    }

    public function children()
    {
        return $this->belongsToMany(Child::class, 'child_guardians')
            ->withPivot('guardian_role_id')
            ->withTimestamps();
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function role()
    {
        return $this->belongsTo(GuardianRole::class);
    }
}
