<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentTrackingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_order_id',
        'status_code',
        'status_label',
        'description',
        'source',
        'event_time',
        'raw_payload_json',
    ];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime',
            'raw_payload_json' => 'array',
        ];
    }

    public function shipmentOrder()
    {
        return $this->belongsTo(ShipmentOrder::class);
    }
}