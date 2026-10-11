<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'purpose',
        'order_no',
        'midtrans_order_id',
        'payment_type',
        'gross_amount',
        'currency',
        'transaction_status',
        'fraud_status',
        'snap_token',
        'snap_redirect_url',
        'status',
        'reference_type',
        'reference_id',
        'paid_at',
        'expired_at',
        'raw_request_json',
        'raw_response_json',
        'settlement_payload_json',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
            'raw_request_json' => 'array',
            'raw_response_json' => 'array',
            'settlement_payload_json' => 'array',
            'meta_json' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeTopup($query)
    {
        return $query->where('purpose', 'topup');
    }

    public function scopeShipping($query)
    {
        return $query->where('purpose', 'shipping');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isTopup(): bool
    {
        return $this->purpose === 'topup';
    }

    public function isShipping(): bool
    {
        return $this->purpose === 'shipping';
    }

    public function shipmentOrder()
    {
        return $this->hasOne(ShipmentOrder::class, 'payment_transaction_id');
    }
}