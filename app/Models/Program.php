<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $table = 'programs';

    protected $fillable = [
        'name',
        'description',
        'price',
        'session_count',
        'clinic_id',
        'program_category_id',
        'order_number',
        'status_id',
    ];

    public function clinic()
    {
        return $this->belongsTo(
            Clinic::class
        );
    }

    public function category()
    {
        return $this->belongsTo(
            ProgramCategory::class,
            'program_category_id'
        );
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function registrationPrograms()
    {
        return $this->hasMany(
            RegistrationProgram::class
        );
    }

    public function registrations()
    {
        return $this->belongsToMany(
            Registration::class,
            'registration_programs'
        )->withPivot('price');
    }
}
