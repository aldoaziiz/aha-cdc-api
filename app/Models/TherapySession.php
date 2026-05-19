<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TherapySession extends Model
{
    protected $fillable = [
        'registration_id',
        'therapist_id',
        'room_id',
        'therapy_date',
        'start_time',
        'end_time',
        'notes',
    ];

    // ======================
    // RELATIONS
    // ======================

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function therapist()
    {
        return $this->belongsTo(Staff::class, 'therapist_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function activity()
    {
        return $this->hasOne(
            Activity::class
        );
    }
}
