<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_no',
        'user_id',
        'address_id',
        'payment_transaction_id',
        'recipient_name_snapshot',
        'recipient_phone_snapshot',
        'address_snapshot_json',
        'shipping_cost',
        'courier_name',
        'service_name',
        'tracking_number',
        'external_order_id',
        'courier_code',
        'service_code',
        'tracking_status',
        'status',
        'requested_at',
        'paid_at',
        'packed_at',
        'shipped_at',
        'delivered_at',
        'booking_failed_at',
        'cancelled_at',
        'external_response_json',
    ];

    protected function casts(): array
    {
        return [
            'address_snapshot_json' => 'array',
            'shipping_cost' => 'decimal:2',
            'requested_at' => 'datetime',
            'paid_at' => 'datetime',
            'packed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'booking_failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'external_response_json' => 'array',
        ];
    }

    public function hasUnresolvedBooking(): bool
    {
        return ! $this->external_order_id
            && (bool) data_get($this->external_response_json, 'booking_attempt.started_at');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function address()
    {
        return $this->belongsTo(UserAddress::class, 'address_id');
    }

    public function trackingEvents()
    {
        return $this->hasMany(ShipmentTrackingEvent::class)->orderBy('event_time');
    }

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function items()
    {
        return $this->hasMany(ShipmentOrderItem::class);
    }
}