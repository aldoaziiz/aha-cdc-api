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
}
