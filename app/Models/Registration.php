<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    protected $fillable = [
        'registration_number',
        'child_id',
        'clinic_id',
        'complaint',
        'program_id',
        'payer_id',
    ];

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function payer()
    {
        return $this->belongsTo(Payer::class);
    }
}
