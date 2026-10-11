<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'recipient_phone',
        'province',
        'city',
        'district',
        'subdistrict',
        'postal_code',
        'full_address',
        'notes',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shipmentOrders()
    {
        return $this->hasMany(ShipmentOrder::class, 'address_id');
    }

    public function getRegionLineAttribute(): string
    {
        return collect([
            $this->province,
            $this->city,
            $this->district,
            $this->subdistrict,
            $this->postal_code,
        ])->filter()->implode(', ');
    }
}