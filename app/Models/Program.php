<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = [
        'name',
        'description'
    ];

    public function children()
    {
        return $this->hasMany(Child::class);
    }
}
