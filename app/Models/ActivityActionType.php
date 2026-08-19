<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityActionType extends Model
{
    protected $fillable = [
        'name',
        'status_id',
    ];

    // ======================
    // RELATIONS
    // ======================

    public function status()
    {
        return $this->belongsTo(
            Status::class
        );
    }

    public function activities()
    {
        return $this->belongsToMany(
            Activity::class,
            'activity_action_assignments',
            'activity_action_type_id',
            'activity_id'
        )->withTimestamps();
    }
}
