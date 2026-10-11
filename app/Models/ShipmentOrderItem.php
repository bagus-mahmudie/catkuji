<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_order_id',
        'inventory_id',
        'prize_name_snapshot',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
        ];
    }

    public function shipmentOrder()
    {
        return $this->belongsTo(ShipmentOrder::class);
    }

    public function inventory()
    {
        return $this->belongsTo(UserInventory::class, 'inventory_id');
    }
}