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
    ];

    public function children()
    {
        return $this->hasMany(Child::class);
    }
}
