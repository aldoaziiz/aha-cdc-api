<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Child extends Model
{
    protected $fillable = [
        'id_number',
        'name',
        'nickname',
        'birth_date',
        'gender',
        'phone',
        'address',
        'program_id',
        'status_id',
        'birthplace_id',
        'hometown_id',
        'education_id',
        'school_id',
        'class_id',
        'guardian_id',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function guardian()
    {
        return $this->belongsTo(Guardian::class, 'guardian_id');
    }

    public function birthplace()
    {
        return $this->belongsTo(City::class, 'birthplace_id');
    }

    public function hometown()
    {
        return $this->belongsTo(City::class, 'hometown_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
