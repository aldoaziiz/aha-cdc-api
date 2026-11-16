<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingItem extends Model
{
    protected $fillable = [
        'billing_id',
        'registration_id',
        'program_id',
        'description',
        'price',
        'quantity',
        'subtotal',
    ];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }
}
