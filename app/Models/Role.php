<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Guardian;

class Role extends Model
{
    protected $fillable = [
        'name',
        'role_id'
    ];

    public function guardians()
    {
        return $this->hasMany(Guardian::class);
    }
}
