<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Child extends Model
{
    protected $fillable = [
        'id_number',
        'name',
        'birth_date',
        'gender',
        'phone',
        'address',
        'program_id',
        'status_id',
        'nickname',
        'birthplace_id',
        'hometown_id',
        'education_id',
        'school_id',
        'class_id',
        'guardian_id',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function guardian()
    {
        return $this->belongsTo(Guardian::class);
    }
}
