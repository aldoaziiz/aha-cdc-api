<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = [
        'therapy_session_id',
        'caption',
        'video',
        'document',
    ];

    // ======================
    // RELATIONS
    // ======================

    public function therapySession()
    {
        return $this->belongsTo(
            TherapySession::class
        );
    }

    public function photos()
    {
        return $this->hasMany(
            ActivityPhoto::class
        );
    }

    public function actionTypes()
    {
        return $this->belongsToMany(
            ActivityActionType::class,
            'activity_action_assignments',
            'activity_id',
            'activity_action_type_id'
        )->withTimestamps();
    }
}
